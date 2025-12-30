<?php

namespace Src\Services;

use Src\Core\App;

class FileService
{
    private const MAX_FILE_SIZE = 2 * 1000 * 1024 * 1024; // 2 Gb
    private const ALLOWED_MIME_TYPES = [
        // Изображения
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',

        // Текст
        'text/plain',

        // PDF
        'application/pdf',

        // Документы Word
        'application/msword', // .doc
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx

        // Электронные таблицы Excel
        'application/vnd.ms-excel', // .xls
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx

        // Презентации PowerPoint
        'application/vnd.ms-powerpoint', // .ppt
        'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx

        // Видео — общие (веб)
        'video/mp4',
        'video/webm',
        'video/ogg',

        // Видео — для фильмов (популярные контейнеры и кодеки)
        'video/x-matroska',       // .mkv — самый популярный контейнер для фильмов
        'video/quicktime',        // .mov — Apple QuickTime, часто используется в киноиндустрии
        'video/x-ms-wmv',         // .wmv — Microsoft Windows Media Video
        'video/x-msvideo',        // .avi — старый, но всё ещё распространённый формат
        'video/mpeg',             // .mpg, .mpeg — MPEG-1/2, иногда используется для DVD
        'video/x-flv',            // .flv — устаревший, но встречается
        'video/x-m4v',            // .m4v — похож на mp4, часто используется Apple

        // Аудио (для полноты, если нужен звук к видео или отдельные файлы)
        'audio/mpeg',             // .mp3
        'audio/wav',
        'audio/ogg',
        'audio/aac',
        'audio/flac',             // .flac — без потерь, популярен у аудиофилов
        'audio/x-ms-wma',         // .wma — Microsoft Windows Media Audio

        // Дополнительно: если планируешь поддерживать 3D-фильмы или специальные форматы
        'video/3gpp',             // .3gp — мобильные видео
        'video/3gpp2',            // .3g2 — улучшенная версия 3GPP
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
                    'folder_id' => $folderId,
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

            // --- Новая логика: Удалить шаринги ---
            $shareService = App::getService('share_by_group_service');
            $shareService->removeSharesForResource('file', $fileId); // Удаляем шаринги для файла
            // ---

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

    // Вспомогательный метод для проверки, была ли папка (или её родительская) расшарена пользователю (email)
    private function isFolderSharedToUser(int $folderId, string $email, $sharedFolderRepo, $folderRepo): bool
    {
        $currentFolderId = $folderId;
        while ($currentFolderId !== null) {
            $sharedFolders = $sharedFolderRepo->findBy('shared_folders', [
                'folder_id' => $currentFolderId,
                'shared_with_email' => $email
            ]);
            if (!empty($sharedFolders)) {
                return true; // Нашли расшаренную папку (email)
            }

            $folder = $folderRepo->find('folders', $currentFolderId);
            if (!$folder) {
                break;
            }
            $currentFolderId = $folder['parent_id'];
        }

        return false; // Ни одна папка в цепочке не была расшарена (email)
    }
}
