<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Controller\Adminhtml\Message;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use GardenLawn\MailSync\Api\MailSenderInterface;
use GardenLawn\MailSync\Model\Config;
use GardenLawn\MailSync\Model\MessageDto;
use GardenLawn\MailSync\Model\MessageFactory;
use GardenLawn\MailSync\Model\ResourceModel\Message as MessageResource;
use GardenLawn\MailSync\Model\FolderFactory;
use GardenLawn\MailSync\Model\ResourceModel\Folder as FolderResource;
use GardenLawn\MailSync\Model\Message\Status;
use DateTimeImmutable;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use GardenLawn\MailSync\Model\Queue\Publisher;

class Send extends Action
{
    public const ADMIN_RESOURCE = 'GardenLawn_MailSync::message';

    public function __construct(
        Context $context,
        private readonly MailSenderInterface $mailSender,
        private readonly Config $config,
        private readonly MessageFactory $messageFactory,
        private readonly MessageResource $messageResource,
        private readonly FolderFactory $folderFactory,
        private readonly FolderResource $folderResource,
        private readonly Filesystem $filesystem,
        private readonly Publisher $publisher
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        $body = $this->getRequest()->getParam('reply_body');
        $to = $this->getRequest()->getParam('to');
        $subject = $this->getRequest()->getParam('subject');
        $websiteIdParam = (int)$this->getRequest()->getParam('website_id');

        if (!$body) {
            $this->messageManager->addErrorMessage(__('Message body is required.'));
            return $this->_redirect('*/*/index');
        }

        try {
            $attachments = [];
            $files = $this->getRequest()->getFiles('reply_attachment');

            if ($files && isset($files['name'])) {
                if (is_array($files['name'])) {
                    $count = count($files['name']);
                    for ($i = 0; $i < $count; $i++) {
                        if ($files['error'][$i] === 0 && $files['size'][$i] > 0) {
                            $content = file_get_contents($files['tmp_name'][$i]);
                            $attachments[] = [
                                'content' => $content,
                                'filename' => $files['name'][$i],
                                'mime' => $files['type'][$i]
                            ];
                        }
                    }
                } else {
                    if ($files['error'] === 0 && $files['size'] > 0) {
                        $content = file_get_contents($files['tmp_name']);
                        $attachments[] = [
                            'content' => $content,
                            'filename' => $files['name'],
                            'mime' => $files['type']
                        ];
                    }
                }
            }

            if ($id) {
                // Reply Mode
                $messageRecord = $this->messageFactory->create();
                $this->messageResource->load($messageRecord, $id);

                if (!$messageRecord->getId()) {
                    throw new \Exception(__('Original message not found.'));
                }

                // Get Website ID from original message's folder
                $folder = $this->folderFactory->create();
                $this->folderResource->load($folder, $messageRecord->getFolderId());
                $websiteId = (int)$folder->getWebsiteId();

                $account = $this->config->getAccount($websiteId);

                $originalMessageDto = new MessageDto(
                    uid: (int)$messageRecord->getUid(),
                    subject: (string)$messageRecord->getSubject(),
                    sender: (string)$messageRecord->getSender(),
                    date: new DateTimeImmutable($messageRecord->getDate()),
                    content: (string)$messageRecord->getContent(),
                    status: Status::tryFrom($messageRecord->getStatus()) ?? Status::READ,
                    folderId: (int)$messageRecord->getFolderId(),
                    type: \GardenLawn\MailSync\Model\Message\Type::PERSONAL,
                    messageId: (string)$messageRecord->getMessageId()
                );

                $this->mailSender->reply($account, $originalMessageDto, $body, $attachments);
                $this->messageManager->addSuccessMessage(__('Reply sent successfully.'));

            } else {
                // New Message Mode
                if (!$to || !$subject) {
                    throw new \Exception(__('Recipient and Subject are required for new messages.'));
                }

                // Use website_id from param or default
                $websiteId = $websiteIdParam ?: 0; // 0 might be admin default, but usually we want a specific store

                $account = $this->config->getAccount($websiteId);

                $this->mailSender->send($account, $to, $subject, $body, $attachments);
                $this->messageManager->addSuccessMessage(__('Message sent successfully.'));
            }

            // Trigger async sync
            $this->publisher->publish();

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error sending message: %1', $e->getMessage()));
        }

        return $this->_redirect('*/*/index');
    }
}
