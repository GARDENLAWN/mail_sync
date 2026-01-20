<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Cron;

use GardenLawn\MailSync\Model\Config;
use GardenLawn\MailSync\Service\ImapSynchronizer;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Sync
{
    public function __construct(
        private readonly Config $config,
        private readonly ImapSynchronizer $synchronizer,
        private readonly LoggerInterface $logger,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function execute(): void
    {
        $websites = $this->storeManager->getWebsites();

        foreach ($websites as $website) {
            try {
                $websiteId = (int)$website->getId();
                $account = $this->config->getAccount($websiteId);

                if (!$account->username || !$account->password) {
                    continue;
                }

                $this->synchronizer->sync($account, $websiteId);
            } catch (\Exception $e) {
                $this->logger->error('MailSync Error (Website ' . $website->getId() . '): ' . $e->getMessage());
            }
        }
    }
}
