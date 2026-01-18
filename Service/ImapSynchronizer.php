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

                    // Determine Message Type
                    $type = Type::PERSONAL;

                    // Check headers for X-Magento-Type
                    // Webklex Header collection
                    $headers = $imapMessage->getHeaders();
                    // Note: Webklex headers might be accessed differently depending on version.
                    // Usually $headers->get('x-magento-type') returns a Header object or string.

                    $magentoTypeHeader = $headers->get('x-magento-type');
                    if ($magentoTypeHeader) {
                        // It might be an object or array or string
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

                } catch (\Exception $e) {
                    if ($output) $output->writeln("  Error processing message UID $uid: " . $e->getMessage());
                }
            }
        }

        $client->disconnect();
    }
}
