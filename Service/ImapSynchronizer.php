<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Service;

use GardenLawn\MailSync\Api\MessageRepositoryInterface;
use GardenLawn\MailSync\Api\FolderRepositoryInterface;
use GardenLawn\MailSync\Model\Account;
use GardenLawn\MailSync\Model\MessageDto;
use GardenLawn\MailSync\Model\Message\Status;
use GardenLawn\MailSync\Model\AttachmentFactory;
use GardenLawn\MailSync\Model\ResourceModel\Attachment as AttachmentResource;
use Webklex\PHPIMAP\ClientManager;
use DateTimeImmutable;
use Symfony\Component\Console\Output\OutputInterface;

class ImapSynchronizer
{
    public function __construct(
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly FolderRepositoryInterface $folderRepository,
        private readonly AttachmentFactory $attachmentFactory,
        private readonly AttachmentResource $attachmentResource
    ) {
    }

    public function sync(Account $account, ?OutputInterface $output = null): void
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
            if ($output) $output->writeln("Processing folder: " . $imapFolder->name);

            $dbFolder = $this->folderRepository->getOrCreate(
                $imapFolder->path,
                $imapFolder->name,
                $imapFolder->delimiter
            );

            $query = $imapFolder->messages()->all();
            $messages = $query->get();

            if ($output) $output->writeln("  Found " . count($messages) . " messages.");

            foreach ($messages as $imapMessage) {
                try {
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

                    $messageDto = new MessageDto(
                        uid: $uid,
                        subject: $subject,
                        sender: $from,
                        date: $dateImmutable,
                        content: substr($content, 0, 5000),
                        status: $status,
                        folderId: (int)$dbFolder->getId(),
                        messageId: $messageId
                    );

                    // Save message and get the ID (we need to modify repository to return ID or use model directly)
                    // The current repository save() returns void. We need the entity_id to link attachments.
                    // Let's assume we modify repository or use a workaround.
                    // For now, I will fetch the ID after save or modify repository.
                    // Best practice: Repository save should return the saved object or ID.

                    $savedMessageId = $this->messageRepository->saveAndReturnId($messageDto);

                    // Process Attachments
                    if ($imapMessage->hasAttachments()) {
                        $attachments = $imapMessage->getAttachments();
                        foreach ($attachments as $attachment) {
                            // Check if attachment already exists for this message
                            // We can use a simple check or unique constraint if we had one on filename+message_id
                            // For now, let's just insert.

                            // Webklex Attachment object properties
                            $filename = $attachment->getName();
                            $mime = $attachment->getMimeType();
                            $size = $attachment->getSize();
                            $partNumber = $attachment->getPartNumber();

                            // Simple check to avoid duplicates if sync runs multiple times
                            // Ideally we should check existence before insert

                            $attModel = $this->attachmentFactory->create();
                            $attModel->setData('message_entity_id', $savedMessageId);
                            $attModel->setData('filename', $filename);
                            $attModel->setData('mime_type', $mime);
                            $attModel->setData('size', $size);
                            $attModel->setData('part_number', $partNumber);

                            // We need a way to check if this specific attachment exists.
                            // Let's assume we clear attachments on re-sync or check.
                            // For this implementation, I'll skip the check for brevity but in prod it's needed.
                            // Actually, let's add a check.

                            $connection = $this->attachmentResource->getConnection();
                            $select = $connection->select()->from($this->attachmentResource->getMainTable())
                                ->where('message_entity_id = ?', $savedMessageId)
                                ->where('part_number = ?', $partNumber);

                            if (!$connection->fetchOne($select)) {
                                $this->attachmentResource->save($attModel);
                            }
                        }
                    }

                } catch (\Exception $e) {
                    if ($output) $output->writeln("  Error processing message UID $uid: " . $e->getMessage());
                }
            }
        }

        $client->disconnect();
    }
}
