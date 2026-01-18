<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model;

use GardenLawn\MailSync\Model\Message\Status;
use GardenLawn\MailSync\Model\Message\Type;
use DateTimeImmutable;

readonly class MessageDto
{
    public function __construct(
        public int $uid,
        public string $subject,
        public string $sender,
        public DateTimeImmutable $date,
        public string $content,
        public Status $status,
        public int $folderId,
        public Type $type,
        public ?string $messageId = null
    ) {
    }
}
