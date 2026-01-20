<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Magento\Framework\App\ResourceConnection;

class ResetCommand extends Command
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('gardenlawn:mail:reset')
            ->setDescription('Reset MailSync database tables (delete all synced data)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Check if helper set is available, if not, skip confirmation or handle differently
        // In Magento 2 DI context, sometimes HelperSet is not fully populated in constructor injection scenarios for commands
        // But usually it works. The error suggests it's missing.

        // Alternative: Use QuestionHelper directly if getHelper fails, but getHelper relies on helperSet.
        // Let's try to instantiate QuestionHelper manually if needed, but standard way is via getHelper.

        // Fix: Ensure parent constructor is called (it is).
        // The issue might be how Magento instantiates commands.

        // Let's try a simpler approach without the helper if it fails, or just force 'y' if interaction is not possible?
        // No, interaction is key.

        // Workaround: Instantiate QuestionHelper directly.
        $helper = $this->getHelperSet() ? $this->getHelper('question') : new \Symfony\Component\Console\Helper\QuestionHelper();

        $question = new ConfirmationQuestion(
            'Are you sure you want to delete ALL synced emails and folders from Magento DB? (y/n) ',
            false
        );

        if (!$helper->ask($input, $output, $question)) {
            return Command::SUCCESS;
        }

        try {
            $connection = $this->resourceConnection->getConnection();

            // Delete folders (cascades to messages and attachments)
            $tableName = $this->resourceConnection->getTableName('gardenlawn_mailsync_folder');

            $output->writeln("Deleting data from $tableName...");
            $connection->delete($tableName); // DELETE FROM table

            // Reset auto_increment
            $output->writeln("Resetting auto_increment...");
            $connection->query("ALTER TABLE $tableName AUTO_INCREMENT = 1");

            // Also reset message table auto_increment just in case
            $msgTable = $this->resourceConnection->getTableName('gardenlawn_mailsync_message');
            $connection->query("ALTER TABLE $msgTable AUTO_INCREMENT = 1");

            $attTable = $this->resourceConnection->getTableName('gardenlawn_mailsync_attachment');
            $connection->query("ALTER TABLE $attTable AUTO_INCREMENT = 1");

            $output->writeln('<info>Reset completed successfully.</info>');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
