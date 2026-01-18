<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model;

use GardenLawn\MailSync\Api\FolderRepositoryInterface;
use GardenLawn\MailSync\Model\ResourceModel\Folder\CollectionFactory;
use GardenLawn\MailSync\Model\FolderFactory;
use GardenLawn\MailSync\Model\ResourceModel\Folder as FolderResource;

class FolderRepository implements FolderRepositoryInterface
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly FolderFactory $folderFactory,
        private readonly FolderResource $folderResource
    ) {
    }

    public function getOrCreate(string $path, string $name, ?string $delimiter = null): Folder
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('path', $path);

        /** @var Folder $folder */
        $folder = $collection->getFirstItem();

        if ($folder->getId()) {
            // Update name if changed (e.g. due to decoding fix)
            if ($folder->getName() !== $name) {
                $folder->setName($name);
                $this->folderResource->save($folder);
            }
            return $folder;
        }

        $folder = $this->folderFactory->create();
        $folder->setData('name', $name);
        $folder->setData('path', $path);
        $folder->setData('delimiter', $delimiter);

        $this->folderResource->save($folder);

        return $folder;
    }
}
