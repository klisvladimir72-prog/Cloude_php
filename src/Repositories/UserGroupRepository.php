<?php

namespace Src\Repositories;

/**
 * Репозиторий для работы с таблицей user_groups
 */
class UserGroupRepository extends BaseRepository
{
    protected string $table = 'user_groups';

    public function findByName(string $name): ?array
    {
        return $this->db->findOneBy($this->table, ['name' => $name]);
    }
}
