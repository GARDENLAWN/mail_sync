<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model\Message;

enum Type: string
{
    case SYSTEM = 'system';
    case PERSONAL = 'personal';
}
