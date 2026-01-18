<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model\ResourceModel\Message;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use GardenLawn\MailSync\Model\Message as MessageModel;
use GardenLawn\MailSync\Model\ResourceModel\Message as MessageResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(MessageModel::class, MessageResource::class);
    }
}
