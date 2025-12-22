<?php

namespace Src\Repositories;

class FolderRepository extends BaseRepository
{
    protected string $table = 'folders';

    // Наследуем все методы из BaseRepository

    public function getTable(): string
    {
        return $this->table;
    }
}
