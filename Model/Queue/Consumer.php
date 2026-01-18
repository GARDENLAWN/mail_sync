<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model\Queue;

use GardenLawn\MailSync\Model\Config;
use GardenLawn\MailSync\Service\ImapSynchronizer;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class Consumer
{
    public function __construct(
        private readonly Config $config,
        private readonly ImapSynchronizer $synchronizer,
        private readonly LoggerInterface $logger,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function process(string $message): void
    {
        // Simple lock mechanism using DB or File
        // Since we use DB queue, let's use a simple flag check or rely on the fact that consumers process sequentially per queue?
        // Magento DB consumers process messages one by one if single thread.
        // But Cron also runs sync. We need to avoid conflict with Cron.

        // Let's use a lock file
        $lockFile = sys_get_temp_dir() . '/gardenlawn_mailsync.lock';

        if (file_exists($lockFile)) {
            // Check if stale (older than 10 mins)
            if (time() - filemtime($lockFile) > 600) {
                unlink($lockFile);
            } else {
                $this->logger->info('MailSync: Sync already running (locked). Skipping queue message.');
                return;
            }
        }

        touch($lockFile);

        try {
            $this->logger->info('MailSync: Starting sync from queue...');
            $account = $this->config->getAccount();
            $this->synchronizer->sync($account);
            $this->logger->info('MailSync: Queue sync completed.');
        } catch (\Exception $e) {
            $this->logger->error('MailSync Queue Error: ' . $e->getMessage());
        } finally {
            if (file_exists($lockFile)) {
                unlink($lockFile);
            }
        }
    }
}
