<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model;

use Magento\Framework\Model\AbstractModel;

class Message extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Message::class);
    }
}
