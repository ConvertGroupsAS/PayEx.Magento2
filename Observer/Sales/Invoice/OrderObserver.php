<?php
/*
 * @author Convert Team
 * @copyright Copyright (c) Convert (https://www.convert.no/)
 */
namespace PayEx\Payments\Observer\Sales\Invoice;

/**
 * Class OrderObserver
 */
class OrderObserver implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Magento\Framework\Lock\Backend\Database
     */
    protected $lockService;

    /**
     * @inheritDoc
     */
    public function __construct(
        \Magento\Framework\Lock\Backend\Database $lockService
    ) {
        $this->lockService = $lockService;
    }

    /**
     * @inheritDoc
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
        $invoice = $observer->getEvent()->getInvoice();

        /** @var \Magento\Sales\Model\Order $order */
        $order = $invoice->getOrder();

        $orderId = $order->getIncrementId();
        if ($this->lockService->isLocked($orderId)) {
            $this->lockService->unlock($orderId);
        }

        return $this;
    }
}
