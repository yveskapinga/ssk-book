<?php

$uri = urldecode((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
$publicFile = __DIR__.'/public'.$uri;

if ($uri !== '/' && is_file($publicFile) && is_readable($publicFile)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/public/index.php';
require __DIR__.'/public/index.php';
