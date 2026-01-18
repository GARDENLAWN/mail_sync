<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model\Queue;

use Magento\Framework\MessageQueue\PublisherInterface;

class Publisher
{
    private const TOPIC_NAME = 'gardenlawn.mail.sync';

    public function __construct(
        private readonly PublisherInterface $publisher
    ) {
    }

    public function publish(): void
    {
        $this->publisher->publish(self::TOPIC_NAME, 'sync_request');
    }
}
