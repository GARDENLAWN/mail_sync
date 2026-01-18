<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Controller\Adminhtml\Message;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use GardenLawn\MailSync\Model\MessageRepository;

class Reply extends Action
{
    public const ADMIN_RESOURCE = 'GardenLawn_MailSync::message';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly MessageRepository $messageRepository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        // We need to load the message to display it in the form
        // The form block will handle loading based on request param 'id'
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GardenLawn_MailSync::messages');
        $resultPage->getConfig()->getTitle()->prepend(__('Reply to Message'));
        return $resultPage;
    }
}
