<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Service;

use GardenLawn\MailSync\Model\Config;
use GardenLawn\MailSync\Model\MessageFactory;
use GardenLawn\MailSync\Model\ResourceModel\Message as MessageResource;
use GardenLawn\MailSync\Model\FolderFactory;
use GardenLawn\MailSync\Model\ResourceModel\Folder as FolderResource;
use Webklex\PHPIMAP\ClientManager;
use Magento\Framework\Exception\LocalizedException;

class MailDeleter
{
    public function __construct(
        private readonly Config $config,
        private readonly MessageFactory $messageFactory,
        private readonly MessageResource $messageResource,
        private readonly FolderFactory $folderFactory,
        private readonly FolderResource $folderResource
    ) {
    }

    public function delete(int $messageId): void
    {
        // 1. Load Message
        $message = $this->messageFactory->create();
        $this->messageResource->load($message, $messageId);

        if (!$message->getId()) {
            throw new LocalizedException(__('Message not found.'));
        }

        // 2. Load Folder to get Website ID and Path
        $folder = $this->folderFactory->create();
        $this->folderResource->load($folder, $message->getFolderId());

        $websiteId = (int)$folder->getWebsiteId();

        // 3. Connect to IMAP
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
            // 4. Find Source Folder
            $imapFolder = null;
            foreach ($client->getFolders() as $f) {
                if ($f->path === $folder->getPath()) {
                    $imapFolder = $f;
                    break;
                }
            }

            if (!$imapFolder) {
                // Try direct fetch
                try {
                    $imapFolder = $client->getFolder($folder->getPath());
                } catch (\Exception $e) {
                    throw new LocalizedException(__('Folder not found on server.'));
                }
            }

            // 5. Find Message
            $imapMessage = $imapFolder->query()->getMessageByUid($message->getUid());

            if ($imapMessage) {
                // 6. Try to find Trash folder
                $trashFolder = $this->findTrashFolder($client);

                if ($trashFolder && $trashFolder->path !== $imapFolder->path) {
                    // Move to Trash
                    $imapMessage->move($trashFolder->path);
                } else {
                    // Hard delete (mark as deleted)
                    $imapMessage->delete();
                }
            }

            // 7. Delete from Magento DB
            $this->messageResource->delete($message);

        } catch (\Exception $e) {
            throw new LocalizedException(__('IMAP Error: %1', $e->getMessage()));
        } finally {
            $client->disconnect();
        }
    }

    private function findTrashFolder($client)
    {
        $trashNames = [
            'Trash', 'Bin', 'Kosz', 'Elementy usunięte', 'Deleted Items', 'Deleted',
            'Papierkorb', 'Corbeille', 'Itens Excluídos'
        ];

        foreach ($client->getFolders() as $folder) {
            // Check exact name or path match
            if (in_array($folder->name, $trashNames) || in_array($folder->path, $trashNames)) {
                return $folder;
            }

            // Check attributes (some servers mark trash with \Trash attribute)
            // Webklex might not expose attributes easily in all versions, relying on name is safer for basic implementation
        }

        // Fuzzy search
        foreach ($client->getFolders() as $folder) {
            if (stripos($folder->name, 'trash') !== false || stripos($folder->name, 'bin') !== false) {
                return $folder;
            }
        }

        return null;
    }
}
