<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Console\Command;

use GardenLawn\MailSync\Model\Config;
use GardenLawn\MailSync\Service\ImapSynchronizer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\State;

class SyncCommand extends Command
{
    private const OPTION_FOLDERS_ONLY = 'folders-only';

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
            ->setDescription('Manually trigger mail synchronization')
            ->addOption(
                self::OPTION_FOLDERS_ONLY,
                'f',
                InputOption::VALUE_NONE,
                'Sync only folder structure, skip messages'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            try {
                $this->state->setAreaCode('adminhtml');
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                // Area code already set
            }

            $foldersOnly = (bool)$input->getOption(self::OPTION_FOLDERS_ONLY);

            $output->writeln('<info>Starting Mail Synchronization...</info>');
            if ($foldersOnly) {
                $output->writeln('<comment>Mode: Folders Only</comment>');
            }

            $account = $this->config->getAccount();

            $output->writeln("Connecting to {$account->imapHost} as {$account->username}...");

            $this->synchronizer->sync($account, $output, $foldersOnly);

            $output->writeln('<info>Synchronization completed successfully.</info>');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            $output->writeln($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
