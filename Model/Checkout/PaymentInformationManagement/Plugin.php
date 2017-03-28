<?php

namespace PayEx\Payments\Model\Checkout\PaymentInformationManagement;

use Magento\Framework\Exception\CouldNotSaveException;

class Plugin
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * @var \Magento\Quote\Api\CartManagementInterface
     */
    protected $cartManagement;

    /**
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Quote\Api\CartManagementInterface $cartManagement
     */
    public function __construct(
        \Magento\Checkout\Model\Session $session,
        \Magento\Quote\Api\CartManagementInterface $cartManagement
    )
    {
        $this->session = $session;
        $this->cartManagement = $cartManagement;
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
        \Magento\Quote\Api\Data\AddressInterface $billingAddress
    )
    {
        if ($paymentMethod->getMethod() === \PayEx\Payments\Model\Method\PartPayment::METHOD_CODE) {
            $additionalData = $paymentMethod->getAdditionalData();
            $this->session->setPayexSSN(isset($additionalData['social_security_number']) ? $additionalData['social_security_number'] : null);
        }
    }
}
