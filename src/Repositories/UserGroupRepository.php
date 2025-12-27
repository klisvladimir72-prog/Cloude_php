<?php

namespace Src\Repositories;

/**
 * Репозиторий для работы с таблицей user_groups
 */
class UserGroupRepository extends BaseRepository
{
    protected string $table = 'user_groups';


    public function findAllGroups(): array
    {
        return parent::findAll($this->table);
    }
}
