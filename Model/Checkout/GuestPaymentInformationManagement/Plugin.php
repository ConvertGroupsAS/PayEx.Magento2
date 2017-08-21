<?php

namespace PayEx\Payments\Model\Checkout\GuestPaymentInformationManagement;

use Magento\Framework\Exception\CouldNotSaveException;

class Plugin
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * @var \Magento\Quote\Api\GuestCartManagementInterface
     */
    protected $cartManagement;

    /**
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Quote\Api\GuestCartManagementInterface $cartManagement
     */
    public function __construct(
        \Magento\Checkout\Model\Session $session,
        \Magento\Quote\Api\GuestCartManagementInterface $cartManagement
    )
    {
        $this->session = $session;
        $this->cartManagement = $cartManagement;
    }

    /**
     * Save Bank Id from payment additional data to session
     * @param \Magento\Checkout\Model\GuestPaymentInformationManagement $subject
     * @param int $cartId
     * @param string $email
     * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
     * @param \Magento\Quote\Api\Data\AddressInterface $billingAddress
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        \Magento\Checkout\Model\GuestPaymentInformationManagement $subject,
        $cartId,
        $email,
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
