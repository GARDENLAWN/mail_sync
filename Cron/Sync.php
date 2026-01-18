<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Cron;

use GardenLawn\MailSync\Model\Config;
use GardenLawn\MailSync\Service\ImapSynchronizer;
use Psr\Log\LoggerInterface;

class Sync
{
    public function __construct(
        private readonly Config $config,
        private readonly ImapSynchronizer $synchronizer,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        try {
            $account = $this->config->getAccount();
            if (!$account->username || !$account->password) {
                return;
            }
            $this->synchronizer->sync($account);
        } catch (\Exception $e) {
            $this->logger->error('MailSync Error: ' . $e->getMessage());
        }
    }
}
