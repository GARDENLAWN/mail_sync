<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Controller\Adminhtml\Message;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'GardenLawn_MailSync::message';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GardenLawn_MailSync::messages');
        $resultPage->getConfig()->getTitle()->prepend(__('Mail Messages'));
        return $resultPage;
    }
}
