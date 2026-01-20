<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model;

readonly class Account
{
    public function __construct(
        public string $username,
        public string $password,
        public string $senderName,
        public string $imapHost,
        public int $imapPort,
        public string $imapEncryption,
        public string $smtpHost,
        public int $smtpPort,
        public string $smtpEncryption,
        public int $websiteId
    ) {
    }
}
