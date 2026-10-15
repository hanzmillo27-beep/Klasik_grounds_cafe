<?php

spl_autoload_register(function (string $class): void {

    $prefixes = [
        'Core\\'        => __DIR__ . '/Core/',
        'Models\\'      => __DIR__ . '/Models/',
        'Controllers\\' => __DIR__ . '/Controllers/',
        'Api\\'         => __DIR__ . '/api/',
    ];

    foreach ($prefixes as $prefix => $directory) {

        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $className = str_replace(
            '\\',
            DIRECTORY_SEPARATOR,
            substr($class, strlen($prefix))
        );
        $file = $directory . $className . '.php';

        if (file_exists($file)) {
            require_once $file;
        }

        return;
    }
});
