<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model\ResourceModel\Attachment;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use GardenLawn\MailSync\Model\Attachment as AttachmentModel;
use GardenLawn\MailSync\Model\ResourceModel\Attachment as AttachmentResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(AttachmentModel::class, AttachmentResource::class);
    }
}
