<?php

namespace PayEx\Payments\Plugin;

use Magento\Framework\Exception\CouldNotSaveException;

class PaymentInformationManagement
{
    /**
     * @var \Magento\Checkout\Helper\Data
     */
    private $checkoutHelper;

    /**
     * @param \Magento\Checkout\Helper\Data $checkoutHelper
     * @param \Magento\Quote\Api\CartManagementInterface $cartManagement
     * @param \Psr\Log\LoggerInterface $logger
     * @param \PayEx\Payments\Helper\Data $payexHelper
     */
    public function __construct(\Magento\Checkout\Helper\Data $checkoutHelper)
    {
        $this->checkoutHelper = $checkoutHelper;
    }

    /**
     * Save Bank Id from payment additional data to session
     * @param \Magento\Checkout\Model\PaymentInformationManagement $subject
     * @param int $cartId
     * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
     * @param \Magento\Quote\Api\Data\AddressInterface $billingAddress
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        \Magento\Checkout\Model\PaymentInformationManagement $subject,
        $cartId,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {
    
        if ($paymentMethod->getMethod() === \PayEx\Payments\Model\Method\PartPayment::METHOD_CODE) {
            $additionalData = $paymentMethod->getAdditionalData();
            $this->checkoutHelper->getCheckout()->setPayexSSN(
                isset($additionalData['social_security_number']) ? $additionalData['social_security_number'] : null
            );
        }
    }
}
