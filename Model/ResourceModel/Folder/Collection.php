<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model\ResourceModel\Folder;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use GardenLawn\MailSync\Model\Folder as FolderModel;
use GardenLawn\MailSync\Model\ResourceModel\Folder as FolderResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(FolderModel::class, FolderResource::class);
    }
}
