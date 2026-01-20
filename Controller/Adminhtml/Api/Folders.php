<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Controller\Adminhtml\Api;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use GardenLawn\MailSync\Model\ResourceModel\Folder\CollectionFactory;
use GardenLawn\MailSync\Model\ResourceModel\Message\CollectionFactory as MessageCollectionFactory;

class Folders extends Action
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly MessageCollectionFactory $messageCollectionFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $websiteId = (int)$this->getRequest()->getParam('website_id');

        $collection = $this->collectionFactory->create();
        // Sort by path to ensure hierarchy order (Parent -> Child)
        $collection->setOrder('path', 'ASC');

        if ($websiteId) {
            $collection->addFieldToFilter('website_id', $websiteId);
        }

        // Get message counts grouped by folder_id
        $counts = $this->getMessageCounts();

        $folders = [];
        foreach ($collection as $folder) {
            $folderId = (int)$folder->getId();
            $folderCounts = $counts[$folderId] ?? ['total' => 0, 'unread' => 0];

            $folders[] = [
                'id' => $folderId,
                'name' => $folder->getName(),
                'path' => $folder->getPath(),
                'delimiter' => $folder->getDelimiter(), // Return delimiter
                'message_count' => (int)$folderCounts['total'],
                'unread_count' => (int)$folderCounts['unread']
            ];
        }

        return $this->jsonFactory->create()->setData($folders);
    }

    private function getMessageCounts(): array
    {
        $messageCollection = $this->messageCollectionFactory->create();
        $select = $messageCollection->getSelect();

        // Reset columns and select count grouped by folder_id
        $select->reset(\Magento\Framework\DB\Select::COLUMNS);
        $select->columns([
            'folder_id',
            'total' => new \Zend_Db_Expr('COUNT(*)'),
            'unread' => new \Zend_Db_Expr("SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END)")
        ]);
        $select->group('folder_id');

        $connection = $messageCollection->getConnection();
        $results = $connection->fetchAll($select);

        $counts = [];
        foreach ($results as $row) {
            $counts[(int)$row['folder_id']] = [
                'total' => $row['total'],
                'unread' => $row['unread']
            ];
        }

        return $counts;
    }
}
