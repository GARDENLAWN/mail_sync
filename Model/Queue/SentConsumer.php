<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model\Queue;

use GardenLawn\MailSync\Model\Config;
use Webklex\PHPIMAP\ClientManager;
use Psr\Log\LoggerInterface;
use Laminas\Mail\Message as LaminasMessage;

class SentConsumer
{
    public function __construct(
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function process(string $rawMessage): void
    {
        try {
            // Parse message to get Website ID header
            $message = LaminasMessage::fromString($rawMessage);
            $headers = $message->getHeaders();

            $websiteId = null;
            if ($headers->has('X-Magento-Website-Id')) {
                $websiteId = (int)$headers->get('X-Magento-Website-Id')->getFieldValue();
            }

            $account = $this->config->getAccount($websiteId);

            $cm = new ClientManager();
            $client = $cm->make([
                'host'          => $account->imapHost,
                'port'          => $account->imapPort,
                'encryption'    => $account->imapEncryption,
                'validate_cert' => false,
                'username'      => $account->username,
                'password'      => $account->password,
                'protocol'      => 'imap'
            ]);

            $client->connect();

            $sentFolder = null;
            // List of possible folder names (decoded and encoded)
            $targetFolderNames = [
                'Sent',
                'Wysłane',
                'Elementy wysłane',
                'Sent Items',
                'Elementy wys&AUI-ane' // Modified UTF-7 for "Elementy wysłane"
            ];

            // Fetch all folders to iterate and check names
            $folders = $client->getFolders();

            foreach ($folders as $folder) {
                // Check against name (usually decoded) or path (sometimes encoded)
                if (in_array($folder->name, $targetFolderNames) || in_array($folder->path, $targetFolderNames)) {
                    $sentFolder = $folder;
                    break;
                }
            }

            if (!$sentFolder) {
                // Fallback: Try to find any folder that looks like Sent using fuzzy search
                foreach ($folders as $folder) {
                    if (stripos($folder->name, 'sent') !== false || stripos($folder->name, 'wysłane') !== false) {
                        $sentFolder = $folder;
                        break;
                    }
                }
            }

            if ($sentFolder) {
                $sentFolder->appendMessage($rawMessage, ['\Seen']);
            } else {
                $this->logger->warning('MailSync SentConsumer: Could not find "Sent" folder to append message.');
            }

            $client->disconnect();

        } catch (\Exception $e) {
            $this->logger->error('MailSync SentConsumer Error: ' . $e->getMessage());
        }
    }
}
