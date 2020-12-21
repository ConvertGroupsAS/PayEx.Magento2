<?php

namespace PayEx\Payments\Controller\Psp;

use Magento\Framework\App\Action\Action;

class Cancel extends Action
{
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
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $orderFactory;

    /**
     * Cancel constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Helper\Data $checkoutHelper
     * @param \PayEx\Payments\Helper\Data $payexHelper
     * @param \PayEx\Payments\Logger\Logger $payexLogger
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \PayEx\Payments\Helper\Data $payexHelper,
        \PayEx\Payments\Logger\Logger $payexLogger,
        \Magento\Sales\Model\OrderFactory $orderFactory
    ) {
        parent::__construct($context);

        $this->urlBuilder = $context->getUrl();
        $this->checkoutHelper = $checkoutHelper;
        $this->payexHelper = $payexHelper;
        $this->payexLogger = $payexLogger;
        $this->orderFactory = $orderFactory;
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $message = __('Order canceled by user');
        $order = $this->getOrder();
        if ($order->getId() && $this->payexHelper->isPayexMethod($order->getPayment()->getMethod())
            && $this->canCancel($order)
        ) {
            $order->cancel();
            $order->addCommentToStatusHistory($message);
            $order->save();
            // Restore the quote
            $this->checkoutHelper->getCheckout()->restoreQuote();
        }
        return $this->_redirect('checkout');
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

    /**
     * Get order object
     * @param \Magento\Sales\Model\Order $order
     */
    protected function canCancel(\Magento\Sales\Model\Order $order)
    {
        return in_array($order->getState(), [
            \Magento\Sales\Model\Order::STATE_NEW,
            \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT,
        ]);
    }
}
