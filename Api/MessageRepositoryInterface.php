<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Api;

use GardenLawn\MailSync\Model\MessageDto;
use GardenLawn\MailSync\Model\Message\Status;

interface MessageRepositoryInterface
{
    /**
     * Save message if not exists
     *
     * @param MessageDto $message
     * @return void
     */
    public function save(MessageDto $message): void;

    /**
     * Save message and return its ID
     *
     * @param MessageDto $message
     * @return int
     */
    public function saveAndReturnId(MessageDto $message): int;

    /**
     * Update message status
     *
     * @param int $uid
     * @param int $folderId
     * @param Status $status
     * @return void
     */
    public function updateStatus(int $uid, int $folderId, Status $status): void;

    /**
     * Get all UIDs for a specific folder
     *
     * @param int $folderId
     * @return array
     */
    public function getUidsByFolderId(int $folderId): array;

    /**
     * Delete messages by UIDs and Folder ID
     *
     * @param array $uids
     * @param int $folderId
     * @return void
     */
    public function deleteByUids(array $uids, int $folderId): void;
}
