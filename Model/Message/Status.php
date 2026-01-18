<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model\Message;

enum Status: string
{
    case READ = 'read';
    case UNREAD = 'unread';
    case FLAGGED = 'flagged';
    case DELETED = 'deleted';
    case ANSWERED = 'answered';
}
