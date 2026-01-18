<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Controller\Adminhtml\Api;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Escaper;
use GardenLawn\MailSync\Model\MessageFactory;
use GardenLawn\MailSync\Model\ResourceModel\Message as MessageResource;
use GardenLawn\MailSync\Model\ResourceModel\Attachment\CollectionFactory as AttachmentCollectionFactory;

class Message extends Action
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly MessageFactory $messageFactory,
        private readonly MessageResource $messageResource,
        private readonly AttachmentCollectionFactory $attachmentCollectionFactory,
        private readonly Escaper $escaper
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        $message = $this->messageFactory->create();
        $this->messageResource->load($message, $id);

        if (!$message->getId()) {
            return $this->jsonFactory->create()->setData(['error' => 'Not found']);
        }

        // Get attachments
        $attachmentCollection = $this->attachmentCollectionFactory->create();
        $attachmentCollection->addFieldToFilter('message_entity_id', $message->getId());

        $attachments = [];
        foreach ($attachmentCollection as $att) {
            $attachments[] = [
                'id' => (int)$att->getId(),
                'filename' => $this->ensureUtf8($att->getFilename()),
                'size' => $att->getSize(),
                'mime' => $att->getMimeType(),
                'download_url' => $this->getUrl('gardenlawn_mailsync/message/download', ['attachment_id' => $att->getId()])
            ];
        }

        $subject = $this->ensureUtf8($message->getSubject());
        $sender = $this->ensureUtf8($message->getSender());
        $content = $this->ensureUtf8((string)$message->getContent());

        // Sanitize HTML content but allow formatting
        // Remove script, iframe, object, embed tags
        $content = preg_replace('/<(script|iframe|object|embed|form)[^>]*>.*?<\/\1>/is', '', $content);
        $content = preg_replace('/<(script|iframe|object|embed|form)[^>]*>/is', '', $content);

        // Remove event handlers (on*)
        $content = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]*)/i', '', $content);

        $data = [
            'id' => (int)$message->getId(),
            'uid' => (int)$message->getUid(),
            'subject' => $subject,
            'sender' => $sender,
            'date' => $message->getDate(),
            'content' => $content, // Return sanitized HTML
            'status' => $message->getStatus(),
            'folder_id' => (int)$message->getFolderId(),
            'attachments' => $attachments
        ];

        return $this->jsonFactory->create()->setData($data);
    }

    private function ensureUtf8(string $string): string
    {
        return mb_convert_encoding($string, 'UTF-8', 'UTF-8');
    }
}
