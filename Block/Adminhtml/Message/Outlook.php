<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Block\Adminhtml\Message;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

class Outlook extends Template
{
    public function __construct(
        Context $context,
        private readonly StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getCurrentWebsiteId(): int
    {
        $websiteId = (int)$this->getRequest()->getParam('website');
        if (!$websiteId) {
            // Default to first website or admin default?
            // Usually 0 is admin, but folders are linked to real websites (ID > 0).
            // Let's try to get default website if not specified.
            $defaultWebsite = $this->storeManager->getDefaultStoreView()->getWebsiteId();
            return (int)$defaultWebsite;
        }
        return $websiteId;
    }
}
