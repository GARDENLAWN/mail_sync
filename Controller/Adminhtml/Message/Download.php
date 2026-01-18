<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Controller\Adminhtml\Message;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use GardenLawn\MailSync\Model\Config;
use GardenLawn\MailSync\Model\AttachmentFactory;
use GardenLawn\MailSync\Model\ResourceModel\Attachment as AttachmentResource;
use GardenLawn\MailSync\Model\MessageFactory;
use GardenLawn\MailSync\Model\ResourceModel\Message as MessageResource;
use GardenLawn\MailSync\Model\FolderFactory;
use GardenLawn\MailSync\Model\ResourceModel\Folder as FolderResource;
use Webklex\PHPIMAP\ClientManager;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;

class Download extends Action
{
    public const ADMIN_RESOURCE = 'GardenLawn_MailSync::message';

    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly AttachmentFactory $attachmentFactory,
        private readonly AttachmentResource $attachmentResource,
        private readonly MessageFactory $messageFactory,
        private readonly MessageResource $messageResource,
        private readonly FolderFactory $folderFactory,
        private readonly FolderResource $folderResource,
        private readonly FileFactory $fileFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $attachmentId = $this->getRequest()->getParam('attachment_id');

        if (!$attachmentId) {
            $this->messageManager->addErrorMessage(__('Invalid attachment ID.'));
            return $this->_redirect('*/*/index');
        }

        try {
            // Load Attachment Metadata
            $attachment = $this->attachmentFactory->create();
            $this->attachmentResource->load($attachment, $attachmentId);

            if (!$attachment->getId()) {
                throw new \Exception(__('Attachment not found.'));
            }

            // Load Message
            $message = $this->messageFactory->create();
            $this->messageResource->load($message, $attachment->getMessageEntityId());

            // Load Folder
            $folder = $this->folderFactory->create();
            $this->folderResource->load($folder, $message->getFolderId());

            // Connect to IMAP
            $account = $this->config->getAccount();
            $cm = new ClientManager();
            $client = $cm->make([
                'host'          => $account->imapHost,
                'port'          => $account->imapPort,
                'encryption'    => $account->imapEncryption,
                'validate_cert' => false,
                'username'      => $account->username,
                'password'      => $account->password,
                'protocol'      => 'imap'
            ]);
            $client->connect();

            // Get Folder and Message
            $imapFolder = $client->getFolder($folder->getPath());

            // Fetch specific message by UID
            $query = $imapFolder->query()->setFetchBody(false)->where('UID', $message->getUid());
            $imapMessage = $query->get()->first();

            if (!$imapMessage) {
                throw new \Exception(__('Message not found on server.'));
            }

            // Find the attachment part
            // Webklex allows getting attachment by part number if we iterate or use specific methods.
            // Since we stored part_number, we can try to find it.

            $targetAttachment = null;
            $attachments = $imapMessage->getAttachments();

            foreach ($attachments as $att) {
                if ($att->getPartNumber() == $attachment->getPartNumber()) {
                    $targetAttachment = $att;
                    break;
                }
            }

            if (!$targetAttachment) {
                throw new \Exception(__('Attachment content not found on server.'));
            }

            // Get content
            $content = $targetAttachment->getContent();
            $filename = $attachment->getFilename();
            $mimeType = $attachment->getMimeType();

            $client->disconnect();

            // Return file download
            return $this->fileFactory->create(
                $filename,
                $content,
                DirectoryList::VAR_DIR,
                $mimeType
            );

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error downloading attachment: %1', $e->getMessage()));
            return $this->_redirect('*/*/index');
        }
    }
}
