<?php

namespace Src\Models;

class Folder
{
    public int $id;
    public string $name;
    public ?int $parent_id;
    public int $user_id;
    public string $created_at;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->name = $data['name'];
        $this->parent_id = $data['parent_id'] ?? null;
        $this->user_id = $data['user_id'];
        $this->created_at = $data['created_at'];
    }
}
