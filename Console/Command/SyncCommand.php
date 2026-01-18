<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Console\Command;

use GardenLawn\MailSync\Model\Config;
use GardenLawn\MailSync\Service\ImapSynchronizer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\State;

class SyncCommand extends Command
{
    public function __construct(
        private readonly Config $config,
        private readonly ImapSynchronizer $synchronizer,
        private readonly State $state
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('gardenlawn:mail:sync')
            ->setDescription('Manually trigger mail synchronization');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Ensure area code is set (needed for some Magento operations)
            try {
                $this->state->setAreaCode('adminhtml');
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                // Area code already set
            }

            $output->writeln('<info>Starting Mail Synchronization...</info>');

            $account = $this->config->getAccount();

            $output->writeln("Connecting to {$account->imapHost} as {$account->username}...");

            $this->synchronizer->sync($account, $output);

            $output->writeln('<info>Synchronization completed successfully.</info>');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            $output->writeln($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
