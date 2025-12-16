<?php

spl_autoload_register(function ($class) {
    $prefix = 'Src\\';
    if (strpos($class, $prefix) === 0) {
        $relative_class = substr($class, strlen($prefix));
        $file = __DIR__ . '/../src/' . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            // Попробуйте закомментировать следующую строку и посмотреть, где именно ошибка
            require $file;
        }
    }
});
