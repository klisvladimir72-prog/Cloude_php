<?php

namespace Src\Models;

class User
{
    public int $id;
    public string $email;
    public string $login;
    public string $password_hash;
    public int $role;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->email = $data['email'];
        $this->login = $data['login'];
        $this->password_hash = $data['password_hash'];
        $this->role = $data['role'] ?? 0;
    }
}
