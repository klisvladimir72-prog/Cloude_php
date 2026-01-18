<?php

namespace Src\Services;

use Src\Core\App;
use Src\Models\User;

class FolderService
{
    private $folderRepo;
    private $fileRepo;
    private $fileService;
    private $shareService;

    public function __construct()
    {
        $this->folderRepo = App::getService('folder_repository');
        $this->fileRepo = App::getService('file_repository');
        $this->folderRepo = App::getService('folder_repository');
        $this->fileService = App::getService('file_service');
        $this->shareService = App::getService('share_by_group_service'); // Для удаления шарингов

    }

    public function createFolder(string $name, int $userId, ?int $parentId = null): bool
    {
        try {
            if ($parentId !== null) {
                $parent = $this->folderRepo->find('folders', $parentId);
                if (!$parent || $parent['user_id'] !== $userId) {
                    error_log("FolderService::createFolder: Parent folder not found or access denied");
                    return false;
                }
            }

            return $this->folderRepo->create([
                'name' => $name,
                'parent_id' => $parentId,
                'user_id' => $userId,
            ]);
        } catch (\Throwable $e) {
            error_log("FolderService::createFolder error: " . $e->getMessage());
            return false;
        }
    }

    public function deleteFolder(int $folderId, User $user): bool
    {
        try {
            $folder = $this->folderRepo->find('folders', $folderId);

            if (!$folder) {
                return false;
            }

            // --- Новая логика: Удалить шаринги для папки перед рекурсивным удалением ---
            $shareService = App::getService('share_by_group_service');
            $shareService->removeSharesForResource('folder', $folderId); // Удаляем шаринги для папки
            // ---

            $this->deleteContentsRecursively($folderId, $user);

            return $this->folderRepo->delete($folderId);
        } catch (\Throwable $e) {
            error_log("FolderService::deleteFolder error: " . $e->getMessage());
            return false;
        }
    }

    private function deleteContentsRecursively(int $folderId, User $user): void
    {
        try {

            $files = $this->fileRepo->findBy($this->fileRepo->getTable(), ['folder_id' => $folderId, 'user_id' => $user->id]);
            foreach ($files as $file) {
                // Удаляем файл (и его шаринги) через его сервис
                $this->fileService->deleteFile($file['id'], $user);
            }

            $subFolders = $this->folderRepo->findBy('folders', ['parent_id' => $folderId, 'user_id' => $user->id]);
            foreach ($subFolders as $subFolder) {
                // Удаляем шаринги для подпапки
                $this->shareService->removeSharesForResource('folder', $subFolder['id']);
                // Рекурсивно удаляем содержимое подпапки
                $this->deleteContentsRecursively($subFolder['id'], $user);
                // Удаляем саму подпапку (и её шаринги)
                $this->deleteFolder($subFolder['id'], $user);
            }
        } catch (\Throwable $e) {
            error_log("FolderService::deleteContentsRecursively error: " . $e->getMessage());
        }
    }

    public function getContentFolder(int $folderId): array
    {
        try {
            $folders = $this->folderRepo->findBy($this->folderRepo->getTable(), ['parent_id' => $folderId]);
            $files = $this->fileRepo->findBy($this->fileRepo->getTable(), ['folder_id' => $folderId]);

            return ['folders' => $folders, 'files' => $files];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function isUniqueFolderNameByParentFolder(int $userId, string|null $parentId, string $folderName): bool
    {

        $success = $this->folderRepo->findBy($this->folderRepo->getTable(), ['name' => $folderName, 'parent_id' => $parentId, 'user_id' => $userId]);

        if ($success) {
            return false;
        }

        return true;
    }

    public function isPermissions(User $user, array $folder): bool
    {

        if ($folder['user_id'] !== $user->id && $user->role !== 1) {
            return false;
        }

        return true;
    }
}
