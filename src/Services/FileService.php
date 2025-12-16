<?php

namespace Src\Services;

use Src\Core\App;

class FileService
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'text/plain',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    public function handleUpload(array $data, array $files, int $userId, ?int $folderId = null)
    {
        try {
            $file = $files['file'] ?? null;

            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                return ['success' => false, 'message' => 'Ошибка загрузки файла.'];
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
                return ['success' => false, 'message' => 'Формат файла не разрешён.'];
            }

            if ($file['size'] > self::MAX_FILE_SIZE) {
                return ['success' => false, 'message' => 'Файл слишком большой.'];
            }

            $uploadDir = __DIR__ . '/../../uploads/';
            $fileName = uniqid() . '_' . basename($file['name']);
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $repo = App::getService('file_repository');
                $repo->create([
                    'original_name' => $file['name'],
                    'filename' => $fileName,
                    'size' => $file['size'],
                    'mime_type' => $mimeType,
                    'user_id' => $userId,
                    'folder_id' => $folderId, // теперь это int или null
                ]);

                return ['success' => true, 'message' => 'Файл успешно загружен.'];
            }

            return ['success' => false, 'message' => 'Не удалось переместить файл.'];
        } catch (\Throwable $e) {
            error_log("FileService::handleUpload error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Внутренняя ошибка сервера'];
        }
    }

    public function deleteFile(int $fileId, int $userId): bool
    {
        try {
            $repo = App::getService('file_repository');
            $file = $repo->find('files', $fileId);

            if (!$file || $file['user_id'] !== $userId) {
                return false;
            }

            $filePath = __DIR__ . '/../../uploads/' . $file['filename'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            return $repo->delete($fileId);
        } catch (\Throwable $e) {
            error_log("FileService::deleteFile error: " . $e->getMessage());
            return false;
        }
    }

    public function prepareDownload(string $fileName, int $userId): ?array
    {
        try {
            $repo = App::getService('file_repository');
            $sharedFolderRepo = App::getService('shared_folder_repository');

            // Находим файл по имени
            $files = $repo->findBy('files', ['filename' => $fileName]);
            $fileRecord = !empty($files) ? $files[0] : null;

            if (!$fileRecord) {
                return null; // Файл не найден
            }

            // Проверяем, является ли текущий пользователь владельцем файла
            if ($fileRecord['user_id'] === $userId) {
                // Владелец - имеет право
                $uploadDir = __DIR__ . '/../../uploads/';
                $filePath = $uploadDir . $fileRecord['filename'];
                if (!file_exists($filePath)) {
                    return null; // Файл физически не существует
                }
                return [
                    'file_record' => $fileRecord,
                    'file_path' => $filePath
                ];
            }

            // Получаем folder_id файла
            $folderId = $fileRecord['folder_id'];

            // Если файл не в папке (корень), проверяем, был ли он расшарен отдельно
            if ($folderId === null) {
                // Проверяем, был ли файл расшарен текущему пользователю
                $sharedFiles = $sharedFolderRepo->getByFileIdAndEmail($fileRecord['id'], $_SESSION['email']); // Используем метод, который нужно реализовать
                if (!empty($sharedFiles)) {
                    $uploadDir = __DIR__ . '/../../uploads/';
                    $filePath = $uploadDir . $fileRecord['filename'];
                    if (!file_exists($filePath)) {
                        return null;
                    }
                    return [
                        'file_record' => $fileRecord,
                        'file_path' => $filePath
                    ];
                }
                return null; // Ни владелец, ни расшарен - нет прав
            }

            // Файл находится в папке. Проверяем, была ли эта папка (или любая из её родительских) расшарена текущему пользователю
            if ($this->isFolderSharedToUser($folderId, $_SESSION['email'], $sharedFolderRepo, $repo)) {
                // Файл находится в расшаренной папке - имеет право на скачивание
                $uploadDir = __DIR__ . '/../../uploads/';
                $filePath = $uploadDir . $fileRecord['filename'];
                if (!file_exists($filePath)) {
                    return null; // Файл физически не существует
                }
                return [
                    'file_record' => $fileRecord,
                    'file_path' => $filePath
                ];
            }

            // Ни владелец, ни в расшаренной папке - нет прав
            return null;
        } catch (\Throwable $e) {
            error_log("FileService::prepareDownload error: " . $e->getMessage());
            return null;
        }
    }

    // Вспомогательный метод для проверки, была ли папка (или её родительская) расшарена пользователю
    private function isFolderSharedToUser(int $folderId, string $email, $sharedFolderRepo, $folderRepo): bool
    {
        // Получаем всю цепочку родительских папок
        $currentFolderId = $folderId;
        while ($currentFolderId !== null) {
            // Проверяем, была ли текущая папка расшарена этому email
            $sharedFolders = $sharedFolderRepo->findBy('shared_folders', [
                'folder_id' => $currentFolderId,
                'shared_with_email' => $email
            ]);
            if (!empty($sharedFolders)) {
                return true; // Нашли расшаренную папку
            }

            // Переходим к родительской папке
            $folder = $folderRepo->find('folders', $currentFolderId);
            if (!$folder) {
                break; // Папка не найдена
            }
            $currentFolderId = $folder['parent_id'];
        }

        return false; // Ни одна папка в цепочке не была расшарена
    }
}
