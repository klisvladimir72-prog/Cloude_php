<?php

namespace Src\Repositories;

class UserRepository extends BaseRepository
{
    protected string $table = 'users';

    public function findByEmail(string $email)
    {
        return $this->db->findOneBy($this->table, ['email' => $email]);
    }
}
