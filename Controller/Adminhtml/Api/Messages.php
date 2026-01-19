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
        $page = (int)$this->getRequest()->getParam('page', 1);
        $limit = (int)$this->getRequest()->getParam('limit', 20);
        $search = $this->getRequest()->getParam('search');

        $collection = $this->collectionFactory->create();

        // 1. Filter by Folder (if provided)
        if ($folderId) {
            $collection->addFieldToFilter('folder_id', $folderId);
        }

        // 2. Filter by Search Query (Sender OR Subject OR Content)
        if ($search) {
            $searchTerm = '%' . $search . '%';
            $collection->addFieldToFilter(
                ['sender', 'subject', 'content'], // Fields
                [
                    ['like' => $searchTerm],      // Condition for sender
                    ['like' => $searchTerm],      // Condition for subject
                    ['like' => $searchTerm]       // Condition for content
                ]
            );
        }

        $collection->setOrder('date', 'DESC');
        $collection->setPageSize($limit);
        $collection->setCurPage($page);

        $messages = [];
        foreach ($collection as $message) {
            $content = (string)$message->getContent();
            $preview = substr(strip_tags($content), 0, 100) . '...';

            // Decode MIME headers and ensure UTF-8
            $subject = $this->decodeMimeHeader($message->getSubject());
            $sender = $this->decodeMimeHeader($message->getSender());
            $preview = $this->ensureUtf8($preview);

            $messages[] = [
                'id' => (int)$message->getId(),
                'subject' => $subject,
                'sender' => $sender,
                'date' => $message->getDate(),
                'status' => $message->getStatus(),
                'preview' => $preview
            ];
        }

        return $this->jsonFactory->create()->setData($messages);
    }

    private function decodeMimeHeader(string $string): string
    {
        // Decode MIME header (e.g. =?UTF-8?B?...)
        $decoded = iconv_mime_decode($string, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        return $this->ensureUtf8($decoded ?: $string);
    }

    private function ensureUtf8(string $string): string
    {
        return mb_convert_encoding($string, 'UTF-8', 'UTF-8');
    }
}
