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
    // Folders to skip (system folders that are not emails or cause issues)
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

        if ($output) $output->writeln("Connected. Fetching folders...");

        $folders = $client->getFolders();

        foreach ($folders as $imapFolder) {
            $folderName = $this->decodeImapUtf7($imapFolder->name);

            if ($output) $output->writeln("Processing folder: " . $folderName . " (" . $imapFolder->path . ")");

            $dbFolder = $this->folderRepository->getOrCreate(
                $imapFolder->path,
                $folderName,
                $imapFolder->delimiter
            );

            if ($foldersOnly) {
                continue;
            }

            // Skip ignored folders
            if (in_array($folderName, self::IGNORED_FOLDERS)) {
                if ($output) $output->writeln("  Skipping ignored folder: $folderName");
                continue;
            }

            try {
                if ($output) $output->writeln("  Fetching messages...");

                // Try to select folder first to ensure it exists and is accessible
                // $imapFolder->examine(); // Optional, might throw error too

                $messages = $imapFolder->query()->limit(50)->get();

                if ($output) $output->writeln("  Found " . count($messages) . " messages.");

                foreach ($messages as $imapMessage) {
                    try {
                        $this->processMessage($imapMessage, $dbFolder, $output);
                    } catch (\Exception $e) {
                        if ($output) $output->writeln("    Error processing message: " . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                if ($output) $output->writeln("  Error fetching messages from folder '$folderName': " . $e->getMessage());
            }
        }

        $client->disconnect();
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

    private function decodeImapUtf7(string $str): string
    {
        if (function_exists('mb_convert_encoding')) {
             return mb_convert_encoding($str, 'UTF-8', 'UTF7-IMAP');
        }
        return $str;
    }
}
