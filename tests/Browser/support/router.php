<?php

$storage = getenv('LARAVEL_STORAGE_PATH');
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

if (is_string($storage) && str_starts_with($uri, '/storage/')) {
    $root = realpath($storage.'/app/public');
    $file = realpath($storage.'/app/public/'.substr($uri, strlen('/storage/')));

    if ($root !== false && $file !== false && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file)) {
        header('Content-Type: '.(mime_content_type($file) ?: 'application/octet-stream'));
        readfile($file);

        return true;
    }

    http_response_code(404);

    return true;
}

return require dirname(__DIR__, 3).'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
