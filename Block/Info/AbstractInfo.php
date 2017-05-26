<?php

namespace PayEx\Payments\Block\Info;

use Magento\Framework\View\Element\Template;

abstract class AbstractInfo extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    protected $_template = 'Magento_Payment::info/default.phtml';

    /**
     * @var array
     */
    protected $transactionFields = [];

    /**
     * @var \PayEx\Payments\Helper\Data
     */
    protected $payexHelper;

    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * @var array
     */
    protected $transactionStatus;

    /**
     * Constructor
     *
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        \PayEx\Payments\Helper\Data $payexHelper,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \PayEx\Payments\Model\Config\Source\TransactionStatus $transactionStatus,
        Template\Context $context,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->payexHelper = $payexHelper;
        $this->transactionStatus = $transactionStatus->toOptionArray();
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * Get some specific information in format of array($label => $value)
     *
     * @return array
     */
    public function getSpecificInformation()
    {
        // Get Payment Info
        /** @var \Magento\Payment\Model\Info $_info */
        $_info = $this->getInfo();
        if ($_info) {
            $transactionId = $_info->getLastTransId();

            if ($transactionId) {
                // Load transaction
                $transaction = $this->transactionRepository->getByTransactionId(
                    $transactionId,
                    $_info->getOrder()->getPayment()->getId(),
                    $_info->getOrder()->getId()
                );

                if ($transaction) {
                    $transaction_data = $transaction->getAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS);
                    if (!$transaction_data) {
                        $payment = $_info->getOrder()->getPayment();
                        $transaction_data = $payment->getMethodInstance()->fetchTransactionInfo($payment, $transactionId);
                        $transaction->setAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, $transaction_data);
                        $transaction->save();
                    }

                    // Filter empty values
                    $transaction_data = array_filter($transaction_data, 'strlen');

                    $result = [];
                    foreach ($this->transactionFields as $description => $list) {
                        foreach ($list as $key => $value) {
                            if (isset($transaction_data[$value])) {
                                $transactionValue = $transaction_data[$value];
                                if ($value == 'transactionStatus') {
                                    if (isset($this->transactionStatus[$transactionValue]) && isset($this->transactionStatus[$transactionValue]['label'])) {
                                        $transactionValue = $this->transactionStatus[$transactionValue]['label'];
                                    }
                                }

                                $result[$description] = $transactionValue;
                            }
                        }
                    }

                    return $result;
                }
            }
        }

        return $this->_prepareSpecificInformation()->getData();
    }
}