<?php

namespace Src\Repositories;

class FileRepository extends BaseRepository
{
    protected string $table = 'files';
    private static array $cache = [];

    public function findBy(string $table, array $criteria)
    {
        $key = $this->table . '_' . md5(serialize($criteria));
        if (isset(self::$cache[$key])) {
            error_log("Cache hit for key: $key");
            return self::$cache[$key];
        }

        $result = parent::findBy($table, $criteria);
        self::$cache[$key] = $result;
        return $result;
    }
}
