<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Api;

use GardenLawn\MailSync\Model\Folder;

interface FolderRepositoryInterface
{
    /**
     * Get or create folder by path
     *
     * @param string $path
     * @param string $name
     * @param string|null $delimiter
     * @return Folder
     */
    public function getOrCreate(string $path, string $name, ?string $delimiter = null): Folder;
}
