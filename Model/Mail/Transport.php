<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model\Mail;

use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Mail\MessageInterface;
use GardenLawn\MailSync\Model\Config;
use GardenLawn\MailSync\Model\Queue\SentPublisher;
use Magento\Store\Model\StoreManagerInterface;
use Laminas\Mail\Message as LaminasMessage;
use Laminas\Mail\Transport\Smtp as SmtpTransport;
use Laminas\Mail\Transport\SmtpOptions;

class Transport implements TransportInterface
{
    public function __construct(
        private readonly MessageInterface $message,
        private readonly Config $config,
        private readonly SentPublisher $sentPublisher,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getMessage(): MessageInterface
    {
        return $this->message;
    }

    public function sendMessage(): void
    {
        try {
            $websiteId = (int)$this->storeManager->getWebsite()->getId();
        } catch (\Exception $e) {
            $websiteId = 0; // Default/Admin
        }

        $account = $this->config->getAccount($websiteId);

        // If no account configured for this website, fallback to default or throw error?
        // Let's assume fallback to default config (websiteId=null in getAccount handles scope logic)
        // Actually getAccount handles scope resolution if we pass websiteId.

        $laminasMessage = null;
        if ($this->message instanceof LaminasMessage) {
            $laminasMessage = $this->message;
        } else {
            $laminasMessage = LaminasMessage::fromString($this->message->getRawMessage());
        }

        if (!$laminasMessage->getFrom()->count()) {
            $laminasMessage->setFrom($account->username, $account->senderName);
        }

        // Mark as SYSTEM message for Sync detection
        $laminasMessage->getHeaders()->addHeaderLine('X-Magento-Type', 'system');
        // Add Website ID header so Consumer knows where to append
        $laminasMessage->getHeaders()->addHeaderLine('X-Magento-Website-Id', (string)$websiteId);

        $options = new SmtpOptions([
            'name' => 'localhost',
            'host' => $account->smtpHost,
            'port' => $account->smtpPort,
            'connection_class' => 'login',
            'connection_config' => [
                'username' => $account->username,
                'password' => $account->password,
                'ssl' => $account->smtpEncryption === 'none' ? null : $account->smtpEncryption,
            ],
        ]);

        $transport = new SmtpTransport();
        $transport->setOptions($options);
        $transport->send($laminasMessage);

        // Async archive to Sent folder
        if (!$laminasMessage->getHeaders()->has('Date')) {
            $laminasMessage->getHeaders()->addHeaderLine('Date', date('r'));
        }
        $this->sentPublisher->publish($laminasMessage->toString());
    }
}
