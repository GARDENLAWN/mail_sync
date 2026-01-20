<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model\Mail;

use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Mail\MessageInterface;
use GardenLawn\MailSync\Model\Config;
use GardenLawn\MailSync\Model\Queue\SentPublisher;
use Laminas\Mail\Message as LaminasMessage;
use Laminas\Mail\Transport\Smtp as SmtpTransport;
use Laminas\Mail\Transport\SmtpOptions;

class Transport implements TransportInterface
{
    public function __construct(
        private readonly MessageInterface $message,
        private readonly Config $config,
        private readonly SentPublisher $sentPublisher
    ) {
    }

    public function getMessage(): MessageInterface
    {
        return $this->message;
    }

    public function sendMessage(): void
    {
        $account = $this->config->getAccount();

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
