<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Service;

use GardenLawn\MailSync\Api\MessageRepositoryInterface;
use GardenLawn\MailSync\Model\Config;
use GardenLawn\MailSync\Model\MessageFactory;
use GardenLawn\MailSync\Model\ResourceModel\Message as MessageResource;
use GardenLawn\MailSync\Model\FolderFactory;
use GardenLawn\MailSync\Model\ResourceModel\Folder as FolderResource;
use Webklex\PHPIMAP\ClientManager;
use Magento\Framework\Exception\LocalizedException;

class MailMover
{
    public function __construct(
        private readonly Config $config,
        private readonly MessageFactory $messageFactory,
        private readonly MessageResource $messageResource,
        private readonly FolderFactory $folderFactory,
        private readonly FolderResource $folderResource,
        private readonly MessageRepositoryInterface $messageRepository
    ) {
    }

    public function move(int $messageId, int $targetFolderId): void
    {
        // Load Message
        $message = $this->messageFactory->create();
        $this->messageResource->load($message, $messageId);

        if (!$message->getId()) {
            throw new LocalizedException(__('Message not found.'));
        }

        // Load Source Folder
        $sourceFolder = $this->folderFactory->create();
        $this->folderResource->load($sourceFolder, $message->getFolderId());

        // Load Target Folder
        $targetFolder = $this->folderFactory->create();
        $this->folderResource->load($targetFolder, $targetFolderId);

        if (!$targetFolder->getId()) {
            throw new LocalizedException(__('Target folder not found.'));
        }

        // Ensure folders belong to same website
        if ($sourceFolder->getWebsiteId() != $targetFolder->getWebsiteId()) {
             throw new LocalizedException(__('Cannot move message between different websites.'));
        }

        $websiteId = (int)$sourceFolder->getWebsiteId();

        // Connect to IMAP
        $account = $this->config->getAccount($websiteId);
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

        try {
            // Get Source Folder on Server
            $imapSourceFolder = null;
            foreach ($client->getFolders() as $f) {
                if ($f->path === $sourceFolder->getPath()) {
                    $imapSourceFolder = $f;
                    break;
                }
            }

            if (!$imapSourceFolder) {
                throw new LocalizedException(__('Source folder not found on server.'));
            }

            // Get Message
            $imapMessage = $imapSourceFolder->query()->getMessageByUid($message->getUid());

            if (!$imapMessage) {
                throw new LocalizedException(__('Message not found on server.'));
            }

            // Move Message
            $status = $imapMessage->move($targetFolder->getPath());

            if (!$status) {
                throw new LocalizedException(__('Failed to move message on server.'));
            }

            // Update DB (Delete old, sync will pick up new)
            $this->messageResource->delete($message);

        } catch (\Exception $e) {
            throw new LocalizedException(__('IMAP Error: %1', $e->getMessage()));
        } finally {
            $client->disconnect();
        }
    }
}
