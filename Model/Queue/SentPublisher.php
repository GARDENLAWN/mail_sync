<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model\Queue;

use Magento\Framework\MessageQueue\PublisherInterface;

class SentPublisher
{
    private const TOPIC_NAME = 'gardenlawn.mail.sent';

    public function __construct(
        private readonly PublisherInterface $publisher
    ) {
    }

    /**
     * Publish raw email content to queue for archiving
     *
     * @param string $rawMessage
     * @return void
     */
    public function publish(string $rawMessage): void
    {
        $this->publisher->publish(self::TOPIC_NAME, $rawMessage);
    }
}
