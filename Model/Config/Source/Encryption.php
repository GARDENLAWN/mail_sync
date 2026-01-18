<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Encryption implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'ssl', 'label' => 'SSL/TLS'],
            ['value' => 'tls', 'label' => 'STARTTLS'],
            ['value' => 'none', 'label' => 'None']
        ];
    }
}
