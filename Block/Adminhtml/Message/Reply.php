<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Block\Adminhtml\Message;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use GardenLawn\MailSync\Model\Message;
use GardenLawn\MailSync\Model\MessageFactory;
use GardenLawn\MailSync\Model\ResourceModel\Message as MessageResource;
use GardenLawn\MailSync\Model\ResourceModel\Attachment\CollectionFactory as AttachmentCollectionFactory;

class Reply extends Template
{
    private ?Message $message = null;

    public function __construct(
        Context $context,
        private readonly MessageFactory $messageFactory,
        private readonly MessageResource $messageResource,
        private readonly AttachmentCollectionFactory $attachmentCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getMessage(): ?Message
    {
        if ($this->message === null) {
            $id = $this->getRequest()->getParam('id');
            if ($id) {
                $message = $this->messageFactory->create();
                $this->messageResource->load($message, $id);
                if ($message->getId()) {
                    $this->message = $message;
                }
            }
        }
        return $this->message;
    }

    public function getAttachments(): array
    {
        $message = $this->getMessage();
        if (!$message) {
            return [];
        }

        $collection = $this->attachmentCollectionFactory->create();
        $collection->addFieldToFilter('message_entity_id', $message->getId());

        return $collection->getItems();
    }

    public function getDownloadUrl(int $attachmentId): string
    {
        return $this->getUrl('*/*/download', ['attachment_id' => $attachmentId]);
    }

    public function getSendUrl(): string
    {
        return $this->getUrl('*/*/send');
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/index');
    }

    public function getFileTypeInfo(string $filename, string $mimeType): array
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Map extensions/mimes to types
        if (in_array($extension, ['pdf'])) {
            return ['class' => 'type-pdf', 'label' => 'PDF'];
        }
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
            return ['class' => 'type-image', 'label' => strtoupper($extension)];
        }
        if (in_array($extension, ['doc', 'docx', 'rtf'])) {
            return ['class' => 'type-word', 'label' => 'DOC'];
        }
        if (in_array($extension, ['xls', 'xlsx', 'csv'])) {
            return ['class' => 'type-excel', 'label' => 'XLS'];
        }
        if (in_array($extension, ['ppt', 'pptx'])) {
            return ['class' => 'type-powerpoint', 'label' => 'PPT'];
        }
        if (in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz'])) {
            return ['class' => 'type-archive', 'label' => 'ZIP'];
        }
        if (in_array($extension, ['php', 'js', 'css', 'html', 'xml', 'json'])) {
            return ['class' => 'type-code', 'label' => 'CODE'];
        }
        if (in_array($extension, ['txt', 'md'])) {
            return ['class' => 'type-text', 'label' => 'TXT'];
        }

        // Fallback to MIME type check if extension is ambiguous
        if (strpos($mimeType, 'image/') === 0) {
            return ['class' => 'type-image', 'label' => 'IMG'];
        }
        if (strpos($mimeType, 'audio/') === 0) {
            return ['class' => 'type-audio', 'label' => 'AUD'];
        }
        if (strpos($mimeType, 'video/') === 0) {
            return ['class' => 'type-video', 'label' => 'VID'];
        }

        return ['class' => 'type-default', 'label' => strtoupper(substr($extension, 0, 3)) ?: 'FILE'];
    }
}
