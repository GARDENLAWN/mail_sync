<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Api;

use GardenLawn\MailSync\Model\Account;
use GardenLawn\MailSync\Model\MessageDto;

interface MailSenderInterface
{
    /**
     * Send a new email
     *
     * @param Account $account
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param array $attachments List of ['content' => string, 'filename' => string, 'mime' => string]
     * @return void
     */
    public function send(Account $account, string $to, string $subject, string $body, array $attachments = []): void;

    /**
     * Reply to an existing message
     *
     * @param Account $account
     * @param MessageDto $originalMessage
     * @param string $body
     * @param array $attachments List of ['content' => string, 'filename' => string, 'mime' => string]
     * @return void
     */
    public function reply(Account $account, MessageDto $originalMessage, string $body, array $attachments = []): void;
}
