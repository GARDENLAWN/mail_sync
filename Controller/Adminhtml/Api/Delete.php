<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Controller\Adminhtml\Api;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use GardenLawn\MailSync\Service\MailDeleter;

class Delete extends Action
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly MailDeleter $mailDeleter
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $messageId = (int)$this->getRequest()->getParam('message_id');

        if (!$messageId) {
            return $this->jsonFactory->create()->setData(['error' => 'Invalid message ID']);
        }

        try {
            $this->mailDeleter->delete($messageId);
            return $this->jsonFactory->create()->setData(['success' => true]);
        } catch (\Exception $e) {
            return $this->jsonFactory->create()->setData(['error' => $e->getMessage()]);
        }
    }
}
