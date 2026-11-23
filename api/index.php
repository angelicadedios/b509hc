<?php
/*
 * VERCEL ROUTER
 * -------------
 * Vercel runs PHP through one entry file. Every page request (for example
 * /patient/book.php) comes here first, and this file loads the matching
 * page from the project. Only real pages can be opened: config.php,
 * includes/ and database/ are never served.
 * On XAMPP this file is not used; Apache opens the pages directly.
 */

$root = dirname(__DIR__);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

// Pictures, CSS and JS are served by Vercel directly. When testing with "php -S", let PHP serve them.
if (strpos($path, '/assets/') === 0 && strpos($path, '..') === false && is_file($root . $path)) {
    return false;
}

if ($path === '' || substr($path, -1) === '/') {
    $path .= 'index.php';
} elseif (substr($path, -4) !== '.php' && is_dir($root . $path)) {
    header('Location: ' . $path . '/', true, 301);
    exit;
}

// Allowed pages: *.php in the main folder (except config.php) and in patient/, staff/, ajax/
$ok = (bool)preg_match('#^/((patient|staff|ajax)/)?[a-z0-9_]+\.php$#', $path)
    && $path !== '/config.php'
    && is_file($root . $path);

if (!$ok) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Page not found</title><body style="font-family:system-ui,sans-serif;padding:40px;text-align:center">'
        . '<h1>Page not found</h1><p><a href="/">Go to the home page</a></p></body>';
    exit;
}

$file = $root . $path;
$_SERVER['SCRIPT_NAME'] = $path;
$_SERVER['PHP_SELF'] = $path;
$_SERVER['SCRIPT_FILENAME'] = $file;
chdir(dirname($file));
unset($root, $ok);
require $file;
