<?php

namespace PayEx\Payments\Model\Config\Source;

class TransactionStatus implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        /* Transaction statuses: 0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
        return  [
            ['value' => 0, 'label' => __('Sale')],
            ['value' => 1, 'label' => __('Initialize')],
            ['value' => 2, 'label' => __('Credit')],
            ['value' => 3, 'label' => __('Authorize')],
            ['value' => 4, 'label' => __('Cancel')],
            ['value' => 5, 'label' => __('Failure')],
            ['value' => 6, 'label' => __('Capture')],
        ];
    }
}
