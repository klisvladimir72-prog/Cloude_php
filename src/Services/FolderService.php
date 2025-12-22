<?php

namespace Src\Services;

use Src\Core\App;

class FolderService
{
    public function createFolder(string $name, int $userId, ?int $parentId = null): bool
    {
        try {
            $repo = App::getService('folder_repository');

            if ($parentId !== null) {
                $parent = $repo->find('folders', $parentId);
                if (!$parent || $parent['user_id'] !== $userId) {
                    error_log("FolderService::createFolder: Parent folder not found or access denied");
                    return false;
                }
            }

            error_log("Creating folder: name=$name, user_id=$userId, parent_id=" . ($parentId ?? 'null'));

            return $repo->create([
                'name' => $name,
                'parent_id' => $parentId,
                'user_id' => $userId,
            ]);
        } catch (\Throwable $e) {
            error_log("FolderService::createFolder error: " . $e->getMessage());
            return false;
        }
    }

    public function deleteFolder(int $folderId, int $userId): bool
    {
        try {
            $repo = App::getService('folder_repository');
            $folder = $repo->find('folders', $folderId);

            if (!$folder || $folder['user_id'] !== $userId) {
                return false;
            }

            // --- Новая логика: Удалить шаринги для папки перед рекурсивным удалением ---
            $shareService = App::getService('share_by_group_service');
            $shareService->removeSharesForResource('folder', $folderId); // Удаляем шаринги для папки
            // ---

            $this->deleteContentsRecursively($folderId);

            return $repo->delete($folderId);
        } catch (\Throwable $e) {
            error_log("FolderService::deleteFolder error: " . $e->getMessage());
            return false;
        }
    }

    private function deleteContentsRecursively(int $folderId): void
    {
        try {
            $fileRepo = App::getService('file_repository');
            $folderRepo = App::getService('folder_repository');
            $fileService = App::getService('file_service');
            $folderService = App::getService('folder_service');
            $shareService = App::getService('share_by_group_service'); // Для удаления шарингов

            $files = $fileRepo->findByFolderIdAndUserId($folderId, $_SESSION['user_id']);
            foreach ($files as $file) {
                // Удаляем файл (и его шаринги) через его сервис
                $fileService->deleteFile($file['id'], $file['user_id']);
            }

            $subFolders = $folderRepo->findBy('folders', ['parent_id' => $folderId, 'user_id' => $_SESSION['user_id']]);
            foreach ($subFolders as $subFolder) {
                // Удаляем шаринги для подпапки
                $shareService->removeSharesForResource('folder', $subFolder['id']);
                // Рекурсивно удаляем содержимое подпапки
                $this->deleteContentsRecursively($subFolder['id']);
                // Удаляем саму подпапку (и её шаринги)
                $folderService->deleteFolder($subFolder['id'], $subFolder['user_id']);
            }
        } catch (\Throwable $e) {
            error_log("FolderService::deleteContentsRecursively error: " . $e->getMessage());
        }
    }
}
