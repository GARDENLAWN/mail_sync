<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Controller\Adminhtml\Api;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use GardenLawn\MailSync\Service\MailMover;
use GardenLawn\MailSync\Model\Queue\Publisher;

class Move extends Action
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly MailMover $mailMover,
        private readonly Publisher $publisher
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $messageId = (int)$this->getRequest()->getParam('message_id');
        $folderId = (int)$this->getRequest()->getParam('folder_id');

        if (!$messageId || !$folderId) {
            return $this->jsonFactory->create()->setData(['error' => 'Invalid parameters']);
        }

        try {
            $this->mailMover->move($messageId, $folderId);

            // Trigger async sync
            $this->publisher->publish();

            return $this->jsonFactory->create()->setData(['success' => true]);
        } catch (\Exception $e) {
            return $this->jsonFactory->create()->setData(['error' => $e->getMessage()]);
        }
    }
}
