<?php
/*
 * SETTINGS
 * --------
 * Online (Vercel): do NOT type passwords here. Put them in
 *   Vercel > your project > Settings > Environment Variables
 * and this file reads them automatically with env().
 *
 * Local (XAMPP): the defaults after the comma are used, so it works
 * without changing anything (root user, no password, database "b509hc").
 */

function env(string $key, $default = '')
{
    $v = getenv($key);
    if ($v === false || $v === '') $v = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    $v = is_string($v) ? trim($v, " \t\n\r\0\x0B\"'") : $v;   // ignore accidental spaces/quotes from copy-paste
    return ($v === null || $v === '') ? $default : $v;
}

define('APP_NAME', 'Barangay 509 Health Center');
define('APP_ENV', env('APP_ENV', 'local'));          // 'local' on XAMPP, 'production' on Vercel
define('APP_URL', rtrim(env('APP_URL', ''), '/'));   // e.g. https://b509hc.vercel.app (used in emails)

// Database (TiDB Cloud online, XAMPP MySQL locally)
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', (int)env('DB_PORT', '3306'));      // TiDB Cloud uses 4000
define('DB_NAME', env('DB_NAME', 'b509hc'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_SSL', env('DB_SSL', '0') === '1');        // TiDB Cloud requires 1

// Google Sign-In
define('GOOGLE_CLIENT_ID', env('GOOGLE_CLIENT_ID', 'PASTE-YOUR-CLIENT-ID.apps.googleusercontent.com'));

// Email for "account approved" messages (Gmail + App Password, free)
define('SMTP_HOST', env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int)env('SMTP_PORT', '465'));
define('SMTP_USER', strtolower(env('SMTP_USER', '')));           // your Gmail address
define('SMTP_PASS', preg_replace('/\s+/', '', env('SMTP_PASS', '')));  // Google shows it with spaces; remove them           // 16-letter Google App Password
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', APP_NAME));

// Optional paid SMS (Semaphore). Leave empty to use in-app notifications only.
define('SMS_API_KEY', env('SMS_API_KEY', ''));
define('SMS_SENDER_NAME', env('SMS_SENDER_NAME', ''));

// Clinic contact (shown on the Privacy Policy page)
define('CLINIC_ADDRESS', env('CLINIC_ADDRESS', 'Barangay 509, Sampaloc, Manila'));
define('CLINIC_EMAIL', env('CLINIC_EMAIL', ''));
define('CLINIC_PHONE', env('CLINIC_PHONE', ''));

// Clinic schedule
define('CLINIC_OPEN', '08:00');
define('CLINIC_CLOSE', '17:00');
define('SLOT_MINUTES', 10);
define('BOOKING_DAYS_AHEAD', 60);

// Uploads are saved in the database. Vercel only accepts about 4.5 MB per request,
// so each file is limited to 2 MB (photos are shrunk automatically in the browser).
define('MAX_UPLOAD_MB', 2);

define('BASE_URL', null);

date_default_timezone_set('Asia/Manila');
