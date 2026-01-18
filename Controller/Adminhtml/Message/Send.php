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
use GardenLawn\MailSync\Model\Message\Status;
use DateTimeImmutable;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

class Send extends Action
{
    public const ADMIN_RESOURCE = 'GardenLawn_MailSync::message';

    public function __construct(
        Context $context,
        private readonly MailSenderInterface $mailSender,
        private readonly Config $config,
        private readonly MessageFactory $messageFactory,
        private readonly MessageResource $messageResource,
        private readonly Filesystem $filesystem
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        $body = $this->getRequest()->getParam('reply_body');

        if (!$id || !$body) {
            $this->messageManager->addErrorMessage(__('Invalid request.'));
            return $this->_redirect('*/*/index');
        }

        try {
            // Handle Attachments
            $attachments = [];
            $files = $this->getRequest()->getFiles('reply_attachment');

            // Check if multiple files were uploaded (array structure differs)
            if ($files && isset($files['name'])) {
                if (is_array($files['name'])) {
                    // Multiple files
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
                    // Single file
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

            // Load original message
            $messageRecord = $this->messageFactory->create();
            $this->messageResource->load($messageRecord, $id);

            if (!$messageRecord->getId()) {
                throw new \Exception(__('Message not found.'));
            }

            $originalMessageDto = new MessageDto(
                uid: (int)$messageRecord->getUid(),
                subject: (string)$messageRecord->getSubject(),
                sender: (string)$messageRecord->getSender(),
                date: new DateTimeImmutable($messageRecord->getDate()),
                content: (string)$messageRecord->getContent(),
                status: Status::tryFrom($messageRecord->getStatus()) ?? Status::READ,
                folderId: (int)$messageRecord->getFolderId(),
                messageId: (string)$messageRecord->getMessageId()
            );

            $account = $this->config->getAccount();

            $this->mailSender->reply($account, $originalMessageDto, $body, $attachments);

            $this->messageManager->addSuccessMessage(__('Reply sent successfully.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error sending reply: %1', $e->getMessage()));
        }

        return $this->_redirect('*/*/index');
    }
}
