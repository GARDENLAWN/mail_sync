<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Controller\Adminhtml\Api;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use GardenLawn\MailSync\Model\ResourceModel\Message\CollectionFactory;

class Messages extends Action
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
        $folderId = $this->getRequest()->getParam('folder_id');

        $collection = $this->collectionFactory->create();
        if ($folderId) {
            $collection->addFieldToFilter('folder_id', $folderId);
        }

        $collection->setOrder('date', 'DESC');
        $collection->setPageSize(50); // Limit for performance

        $messages = [];
        foreach ($collection as $message) {
            $messages[] = [
                'id' => (int)$message->getId(),
                'subject' => $message->getSubject(),
                'sender' => $message->getSender(),
                'date' => $message->getDate(),
                'status' => $message->getStatus(),
                'preview' => substr(strip_tags((string)$message->getContent()), 0, 100) . '...'
            ];
        }

        return $this->jsonFactory->create()->setData($messages);
    }
}
