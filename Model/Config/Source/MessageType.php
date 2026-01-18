<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use GardenLawn\MailSync\Model\Message\Type;

class MessageType implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Type::PERSONAL->value, 'label' => 'Personal'],
            ['value' => Type::SYSTEM->value, 'label' => 'System']
        ];
    }
}
