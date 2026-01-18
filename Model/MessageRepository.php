<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model;

use GardenLawn\MailSync\Api\MessageRepositoryInterface;
use GardenLawn\MailSync\Model\Message\Status;
use GardenLawn\MailSync\Model\ResourceModel\Message as MessageResource;
use Magento\Framework\Exception\CouldNotSaveException;
use Exception;
use function Magento\Framework\__;

class MessageRepository implements MessageRepositoryInterface
{
    public function __construct(
        private readonly MessageResource $resource
    ) {
    }

    public function save(MessageDto $message): void
    {
        $this->saveAndReturnId($message);
    }

    public function saveAndReturnId(MessageDto $message): int
    {
        try {
            $connection = $this->resource->getConnection();
            $tableName = $this->resource->getMainTable();

            // Check if message already exists using unique constraint (uid, folder_id)
            $select = $connection->select()
                ->from($tableName, 'entity_id')
                ->where('uid = ?', $message->uid)
                ->where('folder_id = ?', $message->folderId);

            $existingId = $connection->fetchOne($select);

            if ($existingId) {
                return (int)$existingId;
            }

            $data = [
                'uid' => $message->uid,
                'message_id' => $message->messageId,
                'subject' => $message->subject,
                'sender' => $message->sender,
                'date' => $message->date->format('Y-m-d H:i:s'),
                'content' => $message->content,
                'status' => $message->status->value,
                'folder_id' => $message->folderId,
                'message_type' => $message->type->value
            ];

            $connection->insert($tableName, $data);
            return (int)$connection->lastInsertId($tableName);
        } catch (Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the message: %1', $exception->getMessage()),
                $exception
            );
        }
    }

    public function updateStatus(int $uid, int $folderId, Status $status): void
    {
        try {
            $connection = $this->resource->getConnection();
            $tableName = $this->resource->getMainTable();

            $connection->update(
                $tableName,
                ['status' => $status->value],
                ['uid = ?' => $uid, 'folder_id = ?' => $folderId]
            );
        } catch (Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not update the message status: %1', $exception->getMessage()),
                $exception
            );
        }
    }
}
