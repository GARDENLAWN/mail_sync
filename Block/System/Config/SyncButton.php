<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Block\System\Config;

use GardenLawn\AdminCommands\Block\System\Config\AbstractCommandButton;

class SyncButton extends AbstractCommandButton
{
    public function getCommandName(): string
    {
        return 'gardenlawn:mail:sync';
    }

    public function getButtonLabel(): string
    {
        return 'Run Synchronization';
    }
}
