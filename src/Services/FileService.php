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

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

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

    public function deleteFile(int $fileId, int $userId, int $userRole): array
    {
        try {
            $repo = App::getService('file_repository');
            $file = $repo->find('files', $fileId);

            if (!$file) {
                return ['success' => false, "message" => "Файл не найден."];
            }

            if ($file['user_id'] !== $userId && $userRole !== 1) {
                return ['success' => false, 'message' => 'Нет прав на удаление файла.'];
            }


            // --- Удалить шаринги ---
            $shareService = App::getService('share_by_group_service');
            $shareService->removeSharesForResource('file', $fileId);

            $filePath = __DIR__ . '/../../uploads/' . $file['filename'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $deleted = $repo->delete($fileId);
            if (!$deleted) {
                return ['success' => false, 'message' => "Ошибка при удалении файла."];
            }

            return ['success' => true, 'message' => "Файл успешно удален."];
        } catch (\Throwable $e) {
            error_log("FileService::deleteFile error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Произошла внутрення ошибка.'];
        }
    }

    public function isUniqueFileNameByUser(int $userId, string $fileNewName): bool
    {
        $fileRepo = App::getService('file_repository');

        $success = $fileRepo->findBy($fileRepo->getTable(), ['original_name' => $fileNewName, 'user_id' => $userId]);

        if ($success) {
            return false;
        }

        return true;
    }

    public function extractFileInfo(string $fileId): array|null
    {
        $fileRepo = App::getService('file_repository');
        $file = $fileRepo->find($fileRepo->getTable(), $fileId);
        $fileName = $file['original_name'];

        if (!$file) {
            return null;
        }

        $info = pathinfo($fileName);

        return [
            'name' => $info['filename'],
            'extension' => strtolower($info['extension']),
            'basename' => $info['basename'],
            'dirname' => $info['dirname'] ?? ".",
        ];
    }
}
