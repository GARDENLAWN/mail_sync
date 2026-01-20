<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Service;

use GardenLawn\MailSync\Api\MailSenderInterface;
use GardenLawn\MailSync\Api\MessageRepositoryInterface;
use GardenLawn\MailSync\Model\Account;
use GardenLawn\MailSync\Model\MessageDto;
use GardenLawn\MailSync\Model\Message\Status;
use GardenLawn\MailSync\Model\Queue\SentPublisher;
use Laminas\Mail\Message as LaminasMessage;
use Laminas\Mail\Transport\Smtp as SmtpTransport;
use Laminas\Mail\Transport\SmtpOptions;
use Laminas\Mime\Message as MimeMessage;
use Laminas\Mime\Part as MimePart;
use Laminas\Mime\Mime;

class MailSender implements MailSenderInterface
{
    public function __construct(
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly SentPublisher $sentPublisher
    ) {
    }

    public function send(Account $account, string $to, string $subject, string $body, array $attachments = []): void
    {
        $mimeMessage = $this->createMimeMessage($body, $attachments);
        $this->sendEmail($account, $to, $subject, $mimeMessage);
        $this->appendSentMessage($account, $to, $subject, $mimeMessage);
    }

    public function reply(Account $account, MessageDto $originalMessage, string $body, array $attachments = []): void
    {
        $subject = $this->prepareSubject($originalMessage->subject);
        $replyBody = $this->prepareBody($originalMessage, $body);

        $mimeMessage = $this->createMimeMessage($replyBody, $attachments);

        // Send email with headers for threading
        $this->sendEmail($account, $originalMessage->sender, $subject, $mimeMessage, $originalMessage);

        // Save to Sent folder
        $this->appendSentMessage($account, $originalMessage->sender, $subject, $mimeMessage);

        // Update original message status
        $this->messageRepository->updateStatus($originalMessage->uid, $originalMessage->folderId, Status::ANSWERED);
    }

    private function createMimeMessage(string $bodyText, array $attachments = []): MimeMessage
    {
        $parts = [];

        // Text Body
        $text = new MimePart($bodyText);
        $text->type = Mime::TYPE_TEXT;
        $text->charset = 'utf-8';
        $text->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
        $parts[] = $text;

        // Attachments
        foreach ($attachments as $attachment) {
            $file = new MimePart($attachment['content']);
            $file->type = $attachment['mime'];
            $file->filename = $attachment['filename'];
            $file->disposition = Mime::DISPOSITION_ATTACHMENT;
            $file->encoding = Mime::ENCODING_BASE64;
            $parts[] = $file;
        }

        $mimeMessage = new MimeMessage();
        $mimeMessage->setParts($parts);

        return $mimeMessage;
    }

    private function sendEmail(Account $account, string $to, string $subject, MimeMessage $mimeMessage, ?MessageDto $originalMessage = null): void
    {
        $message = new LaminasMessage();
        $message->setBody($mimeMessage);
        $message->setFrom($account->username, $account->senderName);
        $message->addTo($to);
        $message->setSubject($subject);
        $message->setEncoding('UTF-8');

        // Generate Message-ID
        $messageId = sprintf('<%s@%s>', md5(uniqid(microtime(), true)), 'gardenlawn.local');
        $message->getHeaders()->addHeaderLine('Message-ID', $messageId);
        $message->getHeaders()->addHeaderLine('Date', date('r'));

        if ($originalMessage && $originalMessage->messageId) {
            $message->getHeaders()->addHeaderLine('In-Reply-To', $originalMessage->messageId);
            $message->getHeaders()->addHeaderLine('References', $originalMessage->messageId);
        }

        $options = new SmtpOptions([
            'name' => 'localhost',
            'host' => $account->smtpHost,
            'port' => $account->smtpPort,
            'connection_class' => 'login',
            'connection_config' => [
                'username' => $account->username,
                'password' => $account->password,
                'ssl' => $account->smtpEncryption === 'none' ? null : $account->smtpEncryption,
            ],
        ]);

        $transport = new SmtpTransport();
        $transport->setOptions($options);
        $transport->send($message);
    }

    private function appendSentMessage(Account $account, string $to, string $subject, MimeMessage $mimeMessage): void
    {
        $message = new LaminasMessage();
        $message->setBody($mimeMessage);
        $message->setFrom($account->username, $account->senderName);
        $message->addTo($to);
        $message->setSubject($subject);
        $message->setEncoding('UTF-8');
        $message->getHeaders()->addHeaderLine('Date', date('r'));

        // Async archive to Sent folder
        $this->sentPublisher->publish($message->toString());
    }

    private function prepareSubject(string $originalSubject): string
    {
        if (stripos($originalSubject, 'Re:') === 0) {
            return $originalSubject;
        }
        return 'Re: ' . $originalSubject;
    }

    private function prepareBody(MessageDto $originalMessage, string $replyBody): string
    {
        $date = $originalMessage->date->format('Y-m-d H:i');
        $sender = $originalMessage->sender;

        $quotedBody = "\n\nOn {$date}, {$sender} wrote:\n";
        $quotedBody .= "> " . str_replace("\n", "\n> ", $originalMessage->content);

        return $replyBody . $quotedBody;
    }
}
