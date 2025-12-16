<?php

namespace Src\Core;

class App
{
    private static array $services = [];

    public static function bind(string $name, callable $resolver): void
    {
        self::$services[$name] = $resolver;
    }

    public static function getService(string $name)
    {
        if (!isset(self::$services[$name])) {
            throw new \Exception("Сервис '$name' не зарегистрирован.");
        }
        return call_user_func(self::$services[$name]);
    }
}
