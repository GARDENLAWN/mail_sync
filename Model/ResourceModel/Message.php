<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Message extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('gardenlawn_mailsync_message', 'entity_id');
    }
}
