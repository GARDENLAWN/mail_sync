<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Api;

use GardenLawn\MailSync\Model\Folder;

interface FolderRepositoryInterface
{
    /**
     * Get or create folder by path and website
     *
     * @param string $path
     * @param string $name
     * @param int $websiteId
     * @param string|null $delimiter
     * @return Folder
     */
    public function getOrCreate(string $path, string $name, int $websiteId, ?string $delimiter = null): Folder;
}
