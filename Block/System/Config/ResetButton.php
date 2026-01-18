<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Block\System\Config;

use GardenLawn\AdminCommands\Block\System\Config\AbstractCommandButton;

class ResetButton extends AbstractCommandButton
{
    public function getCommandName(): string
    {
        return 'gardenlawn:mail:reset';
    }

    public function getButtonLabel(): string
    {
        return 'Reset Database';
    }
}
