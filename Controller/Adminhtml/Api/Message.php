<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Controller\Adminhtml\Api;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
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
        private readonly AttachmentCollectionFactory $attachmentCollectionFactory
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
                'filename' => $att->getFilename(),
                'size' => $att->getSize(),
                'mime' => $att->getMimeType(),
                'download_url' => $this->getUrl('gardenlawn_mailsync/message/download', ['attachment_id' => $att->getId()])
            ];
        }

        $data = [
            'id' => (int)$message->getId(),
            'uid' => (int)$message->getUid(),
            'subject' => $message->getSubject(),
            'sender' => $message->getSender(),
            'date' => $message->getDate(),
            'content' => nl2br($this->_escaper->escapeHtml($message->getContent())), // Safe HTML
            'status' => $message->getStatus(),
            'folder_id' => (int)$message->getFolderId(),
            'attachments' => $attachments
        ];

        return $this->jsonFactory->create()->setData($data);
    }
}
