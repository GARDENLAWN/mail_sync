<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Controller\Adminhtml\Api;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use GardenLawn\MailSync\Model\ResourceModel\Folder\CollectionFactory;

class Folders extends Action
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder('name', 'ASC');

        $folders = [];
        foreach ($collection as $folder) {
            $folders[] = [
                'id' => (int)$folder->getId(),
                'name' => $folder->getName(),
                'path' => $folder->getPath()
            ];
        }

        return $this->jsonFactory->create()->setData($folders);
    }
}
