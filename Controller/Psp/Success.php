<?php

namespace PayEx\Payments\Controller\Psp;

use Magento\Framework\App\Action\Action;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Payment\Transaction;

class Success extends Action
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $urlBuilder;

    /**
     * @var \Magento\Checkout\Helper\Data
     */
    private $checkoutHelper;

    /**
     * @var \PayEx\Payments\Helper\Data
     */
    private $payexHelper;

    /**
     * @var \PayEx\Payments\Logger\Logger
     */
    private $payexLogger;

    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    private $orderSender;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $orderFactory;

    /**
     * @var \PayEx\Payments\Helper\Psp
     */
    protected $psp;

    /**
     * @var \PayEx\Payments\Model\PayexTransaction
     */
    protected $payexTransaction;

    /**
     * @var \Magento\Framework\Lock\Backend\Database
     */
    protected $lockService;

    /**
     * Success constructor.
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Helper\Data $checkoutHelper
     * @param \PayEx\Payments\Helper\Data $payexHelper
     * @param \PayEx\Payments\Logger\Logger $payexLogger
     * @param \PayEx\Payments\Helper\Psp $psp
     * @param \PayEx\Payments\Model\PayexTransaction $payexTransaction
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Framework\Lock\Backend\Database $lockService
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \PayEx\Payments\Helper\Data $payexHelper,
        \PayEx\Payments\Logger\Logger $payexLogger,
        \PayEx\Payments\Helper\Psp $psp,
        \PayEx\Payments\Model\PayexTransaction $payexTransaction,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Lock\Backend\Database $lockService
    ) {

        parent::__construct($context);

        $this->logger = $logger;
        $this->urlBuilder = $context->getUrl();
        $this->checkoutHelper = $checkoutHelper;
        $this->payexHelper = $payexHelper;
        $this->payexLogger = $payexLogger;
        $this->transactionRepository = $transactionRepository;
        $this->orderSender = $orderSender;
        $this->orderFactory = $orderFactory;
        $this->psp = $psp;
        $this->payexTransaction = $payexTransaction;
        $this->lockService = $lockService;
    }


    /**
     * Dispatch request
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\InputException
     */
    public function execute()
    {
        // Load Order
        $order = $this->getOrder();
        if (!$order->getId()) {
            $this->checkoutHelper->getCheckout()->restoreQuote();
            $this->messageManager->addError(__('No order for processing found'));
            $this->_redirect('checkout');
            return;
        }

        // Remove Redirect Url from Session
        $this->checkoutHelper->getCheckout()->unsPayexRedirectUrl();

        $order_id = $order->getIncrementId();

        /** @var string $payment_id */
        $payment_id = $order->getPayment()->getAdditionalInformation('payex_payment_id');
        if (empty($payment_id)) {
            $this->checkoutHelper->getCheckout()->restoreQuote();
            $this->messageManager->addError(__('Unable to get payment Id'));
            $this->_redirect('checkout');
            return;
        }


        /** @var \Magento\Payment\Model\Method\AbstractMethod $method */
        $method = $order->getPayment()->getMethodInstance();

        // Override PSP helper
        $this->psp = $method->getPsp();

        // Fetch payment info
        try {
            $result = $this->psp->request('GET', $payment_id);
        } catch (\Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }

        // Check payment state
        if (in_array($result['payment']['state'], ['Failed', 'Aborted'])) {
            // Cancel order
            $message = __('Payment canceled with state %1.', $result['payment']['state']);
            $this->payexHelper->cancelOrder($order, $message);
            $order->save();

            // Restore the quote
            $this->checkoutHelper->getCheckout()->restoreQuote();

            $this->_redirect('checkout');
        }
        try {
            $this->lockService->lock($order_id, Callback::LOCK_TIMEOUT);
            // Fetch transactions list
            $result = $this->psp->request('GET', $payment_id . '/transactions');
            $transactions = $result['transactions']['transactionList'];

            // Import transactions
            $this->payexTransaction->import_transactions($transactions, $order_id);

            // Check payment is authorized
            $transactions = $this->payexTransaction->select([
                'order_id' => $order_id,
                'type'     => 'Authorization'
            ]);

            if ($transaction = $this->psp->filter($transactions, ['state' => 'Completed'])) {
                // Check Transaction is already registered
                $trans = $this->transactionRepository->getByTransactionId(
                    $transaction['number'],
                    $order->getPayment()->getId(),
                    $order->getId()
                );

                // Register Transaction
                if (!$trans) {
                    $order->getPayment()->setTransactionId($transaction['number']);
                    $trans = $order->getPayment()->addTransaction(Transaction::TYPE_AUTH, null, true);
                    $trans->setIsClosed(0);
                    $trans->setAdditionalInformation(Transaction::RAW_DETAILS, $transaction);
                    $trans->save();

                    // Set Last Transaction ID
                    $order->getPayment()->setLastTransId($transaction['number'])->save();

                    // Send order notification
                    $this->orderSender->send($order);
                }

                // Payment authorized
                // Change order status
                $new_status = $method->getConfigData('order_status_authorize');

                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payexHelper->getAssignedState($new_status);
                $order->setData('state', $status->getState());
                $order->addStatusHistoryComment(__('Payment has been authorized'), $status->getStatus());

                $this->_eventManager->dispatch('payex_psp_payment_authorized', [
                    'order' => $order
                ]);

                // Check payment is authorized
                $captureTransactions = $this->payexTransaction->select([
                    'order_id' => $order_id,
                    'type'     => 'Capture'
                ]);

                if ($captureTransaction = $this->psp->filter($captureTransactions, ['state' => 'Completed'])) {
                    $order->getPayment()->setTransactionId($captureTransaction['number']);
                    $trans = $order->getPayment()->addTransaction(Transaction::TYPE_CAPTURE, null, true);
                    $trans->setIsClosed(0);
                    $trans->setAdditionalInformation(Transaction::RAW_DETAILS, $captureTransaction);
                    $trans->save();

                    // Set Last Transaction ID
                    $order->getPayment()->setLastTransId($captureTransaction['number'])->save();

                    // Change order status
                    $new_status = $method->getConfigData('order_status_capture');

                    /** @var \Magento\Sales\Model\Order\Status $status */
                    $status = $this->payexHelper->getAssignedState($new_status);
                    $order->setData('state', $status->getState());
                    $order->setStatus($status->getStatus());
                    $order->save();

                    $order->addStatusHistoryComment(__('Payment has been captured'));

                    // Send order notification
                    $this->orderSender->send($order);

                    // Create Invoice
                    if ($order->canInvoice()) {
                        $invoice = $this->payexHelper->makeInvoice(
                            $order,
                            [],
                            false
                        );
                        $invoice->setTransactionId($captureTransaction['number']);
                        $invoice->save();
                    }
                    $this->logger->info(sprintf('IPN: Order #%s marked as captured', $order_id));
                }

                $order->save();

                // Redirect to Success page
                $this->checkoutHelper->getCheckout()->getQuote()->setIsActive(false)->save();
                $this->_redirect('checkout/onepage/success');
            } elseif ($transaction = $this->psp->filter($transactions, ['state' => 'Failed'])) {
                // @todo Cancel Order ?
                // @todo Extract failed reason
                // Restore the quote
                $this->checkoutHelper->getCheckout()->restoreQuote();
                $this->_redirect('checkout');
            } else {
                // Pending?
                // Redirect to Success page
                $this->checkoutHelper->getCheckout()->getQuote()->setIsActive(false)->save();
                $this->_redirect('checkout/onepage/success');
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        } finally {
            $this->lockService->unlock($order_id);
        }

        return $this->_response;
    }

    /**
     * Get order object
     * @return \Magento\Sales\Model\Order
     */
    protected function getOrder()
    {
        $incrementId = $this->checkoutHelper->getCheckout()->getLastRealOrderId();
        return $this->orderFactory->create()->loadByIncrementId($incrementId);
    }
}
