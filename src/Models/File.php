<?php

namespace Src\Models;

class File
{
    public int $id;
    public string $original_name;
    public string $filename;
    public int $size;
    public string $mime_type;
    public int $user_id;
    public ?int $folder_id;
    public string $created_at;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->original_name = $data['original_name'];
        $this->filename = $data['filename'];
        $this->size = $data['size'];
        $this->mime_type = $data['mime_type'];
        $this->user_id = $data['user_id'];
        $this->folder_id = $data['folder_id'] ?? null;
        $this->created_at = $data['created_at'];
    }
}
