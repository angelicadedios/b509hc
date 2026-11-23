<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

if (APP_ENV === 'local') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/validation.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

start_session();
ensure_schema();
mark_missed();
