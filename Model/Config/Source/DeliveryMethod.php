<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for the allowed delivery methods admin config field.
 */
class DeliveryMethod implements OptionSourceInterface
{
    /**
     * Return option array for the delivery methods select.
     *
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'email', 'label' => __('Email only')],
            ['value' => 'sms',   'label' => __('SMS only')],
            ['value' => 'both',  'label' => __('Both (customer chooses)')],
        ];
    }
}
