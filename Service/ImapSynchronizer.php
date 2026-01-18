<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Service;

use GardenLawn\MailSync\Api\MessageRepositoryInterface;
use GardenLawn\MailSync\Api\FolderRepositoryInterface;
use GardenLawn\MailSync\Model\Account;
use GardenLawn\MailSync\Model\MessageDto;
use GardenLawn\MailSync\Model\Message\Status;
use GardenLawn\MailSync\Model\Message\Type;
use GardenLawn\MailSync\Model\AttachmentFactory;
use GardenLawn\MailSync\Model\ResourceModel\Attachment as AttachmentResource;
use Webklex\PHPIMAP\ClientManager;
use DateTimeImmutable;
use Symfony\Component\Console\Output\OutputInterface;

class ImapSynchronizer
{
    private const IGNORED_FOLDERS = [
        'Kalendarz', 'Calendar',
        'Kontakty', 'Contacts',
        'Notatki', 'Notes',
        'Zadania', 'Tasks',
        'Dziennik', 'Journal'
    ];

    public function __construct(
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly FolderRepositoryInterface $folderRepository,
        private readonly AttachmentFactory $attachmentFactory,
        private readonly AttachmentResource $attachmentResource
    ) {
    }

    public function sync(Account $account, ?OutputInterface $output = null, bool $foldersOnly = false): void
    {
        // Step 1: Fetch Folder List
        $folderList = [];
        try {
            $client = $this->createClient($account);
            $client->connect();
            if ($output) $output->writeln("Connected. Fetching folder list...");

            $folders = $client->getFolders();
            foreach ($folders as $f) {
                $folderList[] = [
                    'name' => $f->name,
                    'path' => $f->path,
                    'delimiter' => $f->delimiter
                ];
            }
            $client->disconnect();
        } catch (\Exception $e) {
            if ($output) $output->writeln("Error fetching folder list: " . $e->getMessage());
            return;
        }

        // Step 2: Process each folder individually
        foreach ($folderList as $folderData) {
            $folderName = $folderData['name'];
            $folderPath = $folderData['path'];

            if ($output) $output->writeln("Processing folder: " . $folderName . " (" . $folderPath . ")");

            $dbFolder = $this->folderRepository->getOrCreate(
                $folderPath,
                $folderName,
                $folderData['delimiter']
            );

            if ($foldersOnly) {
                continue;
            }

            if (in_array($folderName, self::IGNORED_FOLDERS)) {
                if ($output) $output->writeln("  Skipping ignored folder: $folderName");
                continue;
            }

            // Reconnect for this folder
            try {
                $client = $this->createClient($account);
                $client->connect();

                // Find folder object safely
                $activeFolder = null;
                foreach ($client->getFolders() as $f) {
                    if ($f->path === $folderPath) {
                        $activeFolder = $f;
                        break;
                    }
                }

                if (!$activeFolder) {
                    if ($output) $output->writeln("  Could not find folder object for '$folderName'. Skipping.");
                    $client->disconnect();
                    continue;
                }

                // Select folder
                $client->openFolder($folderPath);

                if ($output) $output->writeln("  Fetching UIDs...");

                // Get all UIDs from server
                $serverUids = $client->getConnection()->search(['ALL'])->validatedData();

                // Prune deleted messages
                $this->pruneDeletedMessages((int)$dbFolder->getId(), $serverUids, $output);

                if (empty($serverUids)) {
                    if ($output) $output->writeln("  Folder is empty.");
                    $client->disconnect();
                    continue;
                }

                rsort($serverUids);
                $uidsToSync = array_slice($serverUids, 0, 20);

                if ($output) $output->writeln("  Found " . count($serverUids) . " messages. Syncing " . count($uidsToSync) . " newest.");

                foreach ($uidsToSync as $uid) {
                    try {
                        $message = $activeFolder->query()->getMessageByUid($uid);
                        $this->processMessage($message, $dbFolder, $output);
                        usleep(50000);
                    } catch (\Exception $e) {
                        if ($output) $output->writeln("    Error processing message UID $uid: " . $e->getMessage());

                        if (!$client->isConnected()) {
                            if ($output) $output->writeln("    Reconnecting...");
                            try {
                                $client->connect();
                                $client->openFolder($folderPath);
                                foreach ($client->getFolders() as $f) {
                                    if ($f->path === $folderPath) {
                                        $activeFolder = $f;
                                        break;
                                    }
                                }
                            } catch (\Exception $reconnectEx) {
                                if ($output) $output->writeln("    Reconnect failed: " . $reconnectEx->getMessage());
                                break;
                            }
                        }
                    }
                }

                $client->disconnect();

            } catch (\Exception $e) {
                if ($output) $output->writeln("  Error processing folder '$folderName': " . $e->getMessage());
                try { $client->disconnect(); } catch (\Exception $ex) {}
            }

            sleep(1);
        }
    }

    private function pruneDeletedMessages(int $folderId, array $serverUids, ?OutputInterface $output): void
    {
        $dbUids = $this->messageRepository->getUidsByFolderId($folderId);

        // Find UIDs that are in DB but not on Server
        $uidsToDelete = array_diff($dbUids, $serverUids);

        if (!empty($uidsToDelete)) {
            if ($output) $output->writeln("  Deleting " . count($uidsToDelete) . " messages from DB (not on server)...");
            $this->messageRepository->deleteByUids($uidsToDelete, $folderId);
        }
    }

    private function createClient(Account $account)
    {
        $cm = new ClientManager();
        return $cm->make([
            'host'          => $account->imapHost,
            'port'          => $account->imapPort,
            'encryption'    => $account->imapEncryption,
            'validate_cert' => false,
            'username'      => $account->username,
            'password'      => $account->password,
            'protocol'      => 'imap',
            'options' => [
                'debug' => false,
            ]
        ]);
    }

    private function processMessage($imapMessage, $dbFolder, $output): void
    {
        $uid = (int)$imapMessage->getUid();
        $messageId = (string)$imapMessage->getMessageId();
        $subject = (string)$imapMessage->getSubject();

        $from = 'unknown';
        if ($imapMessage->getFrom() && isset($imapMessage->getFrom()[0])) {
            $from = $imapMessage->getFrom()[0]->mail;
        }

        $date = $imapMessage->getDate();

        $content = '';
        if ($imapMessage->hasTextBody()) {
            $content = $imapMessage->getTextBody();
        } elseif ($imapMessage->hasHTMLBody()) {
            $content = strip_tags($imapMessage->getHTMLBody());
        }

        $flags = $imapMessage->getFlags();
        $status = Status::UNREAD;

        if ($flags->has('seen')) {
            $status = Status::READ;
        }
        if ($flags->has('flagged')) {
            $status = Status::FLAGGED;
        }
        if ($flags->has('deleted')) {
            $status = Status::DELETED;
        }

        if ($date instanceof DateTimeImmutable) {
            $dateImmutable = $date;
        } elseif ($date instanceof \DateTime) {
            $dateImmutable = DateTimeImmutable::createFromMutable($date);
        } else {
            try {
                $dateImmutable = new DateTimeImmutable((string)$date);
            } catch (\Exception $e) {
                $dateImmutable = new DateTimeImmutable();
            }
        }

        $type = Type::PERSONAL;

        $headers = $imapMessage->getHeaders();
        $magentoTypeHeader = $headers->get('x-magento-type');
        if ($magentoTypeHeader) {
            $headerValue = is_object($magentoTypeHeader) ? $magentoTypeHeader->getValue() : $magentoTypeHeader;
            if (is_string($headerValue) && strtolower($headerValue) === 'system') {
                $type = Type::SYSTEM;
            }
        }

        $messageDto = new MessageDto(
            uid: $uid,
            subject: $subject,
            sender: $from,
            date: $dateImmutable,
            content: substr($content, 0, 5000),
            status: $status,
            folderId: (int)$dbFolder->getId(),
            type: $type,
            messageId: $messageId
        );

        $savedMessageId = $this->messageRepository->saveAndReturnId($messageDto);

        if ($imapMessage->hasAttachments()) {
            $attachments = $imapMessage->getAttachments();
            foreach ($attachments as $attachment) {
                $filename = $attachment->getName();
                $mime = $attachment->getMimeType();
                $size = $attachment->getSize();
                $partNumber = $attachment->getPartNumber();

                $connection = $this->attachmentResource->getConnection();
                $select = $connection->select()->from($this->attachmentResource->getMainTable())
                    ->where('message_entity_id = ?', $savedMessageId)
                    ->where('part_number = ?', $partNumber);

                if (!$connection->fetchOne($select)) {
                    $attModel = $this->attachmentFactory->create();
                    $attModel->setData('message_entity_id', $savedMessageId);
                    $attModel->setData('filename', $filename);
                    $attModel->setData('mime_type', $mime);
                    $attModel->setData('size', $size);
                    $attModel->setData('part_number', $partNumber);
                    $this->attachmentResource->save($attModel);
                }
            }
        }

        if ($output) $output->writeln("    Processed UID: $uid");
    }
}
