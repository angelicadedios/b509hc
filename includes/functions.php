<?php
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        if (DB_SSL) {
            // TiDB Cloud only accepts encrypted connections. Use the server's trusted certificates.
            $sslCa  = class_exists('Pdo\\Mysql') ? constant('Pdo\\Mysql::ATTR_SSL_CA') : PDO::MYSQL_ATTR_SSL_CA;
            $verify = class_exists('Pdo\\Mysql') ? constant('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT') : PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT;
            foreach (['/etc/pki/tls/certs/ca-bundle.crt', '/etc/ssl/certs/ca-certificates.crt', '/etc/ssl/cert.pem'] as $ca) {
                if (is_file($ca)) { $opts[$sslCa] = $ca; break; }
            }
            $opts[$verify] = true;
        }
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
            $pdo->exec("SET time_zone = '+08:00'");
        } catch (PDOException $e) {
            http_response_code(500);
            if (APP_ENV === 'local') {
                exit('Database connection failed: ' . htmlspecialchars($e->getMessage())
                    . '<br>Check the DB_ settings in config.php and that you imported database/schema.sql.');
            }
            error_log('DB connection failed: ' . $e->getMessage());
            exit('The system is temporarily unavailable. Please try again later.');
        }
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function base_path(): string
{
    static $base = null;
    if ($base !== null) return $base;
    if (BASE_URL !== null) return $base = rtrim(BASE_URL, '/');
    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $app  = realpath(__DIR__ . '/..') ?: '';
    $rel  = ($root !== '' && strpos($app, $root) === 0) ? substr($app, strlen($root)) : '';
    return $base = rtrim(str_replace('\\', '/', $rel), '/');
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, ?array $src = null): string
{
    $src = $src ?? $_POST;
    $v = $src[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

/*
 * Sessions are saved in the database (table "sessions") instead of files.
 * On Vercel every request can run on a different server, so file sessions
 * would log people out at random.
 */
class DbSessionHandler implements SessionHandlerInterface
{
    public function open($path, $name): bool { return true; }
    public function close(): bool { return true; }

    public function read($id): string|false
    {
        $row = q('SELECT data FROM sessions WHERE id = ? AND expires_at > ?', [$id, time()])->fetch();
        return $row ? (string)$row['data'] : '';
    }

    public function write($id, $data): bool
    {
        q('REPLACE INTO sessions (id, data, expires_at) VALUES (?, ?, ?)', [$id, $data, time() + SESSION_LIFETIME]);
        return true;
    }

    public function destroy($id): bool
    {
        q('DELETE FROM sessions WHERE id = ?', [$id]);
        return true;
    }

    public function gc($max): int|false
    {
        return q('DELETE FROM sessions WHERE expires_at < ?', [time()])->rowCount();
    }
}

const SESSION_LIFETIME = 60 * 60 * 8; // 8 hours of inactivity

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = APP_ENV === 'production'
        || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_save_handler(new DbSessionHandler(), true);
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    ini_set('session.use_strict_mode', '1');
    session_name('B509SESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Your session expired or the form was tampered with. Please go back, refresh the page and try again.');
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role']    = $user['role'];
}

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) return $user;
    if (empty($_SESSION['user_id'])) return $user = null;
    $user = q('SELECT id, role, is_admin, is_active, approval_status, review_note, deactivated_reason, email, display_name FROM users WHERE id = ?', [$_SESSION['user_id']])->fetch() ?: null;
    if ($user && !(int)$user['is_active']) {

        unset($_SESSION['user_id'], $_SESSION['role']);
        $user = null;
    }
    return $user;
}

function dev_login_allowed(): bool
{
    $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
    return APP_ENV === 'local' && in_array($host, ['localhost', '127.0.0.1'], true);
}

function require_admin(): array
{
    $u = require_role('staff');
    if (!(int)$u['is_admin']) {
        flash('error', 'Only administrators can manage staff accounts.');
        redirect('staff/index.php');
    }
    return $u;
}

function ensure_schema(): void
{
    if (!empty($_SESSION['schema_v3'])) return;
    $admins = (int)q("SELECT COUNT(*) FROM users WHERE role = 'staff' AND is_admin = 1 AND is_active = 1")->fetchColumn();
    if ($admins === 0) {
        q("UPDATE users SET is_admin = 1, is_active = 1 WHERE role = 'staff' ORDER BY id LIMIT 1");
    }
    $_SESSION['schema_v3'] = true;
}

function active_admin_count(): int
{
    return (int)q("SELECT COUNT(*) FROM users WHERE role = 'staff' AND is_admin = 1 AND is_active = 1")->fetchColumn();
}

function require_role(string $role): array
{
    $u = current_user();
    if (!$u) {
        flash('info', 'Please log in first.');
        redirect($role === 'staff' ? 'staff_login.php' : 'login.php');
    }
    if ($u['role'] !== $role) {
        redirect($u['role'] === 'staff' ? 'staff/index.php' : 'patient/bookings.php');
    }
    return $u;
}

function full_name(array $p): string
{
    $name = $p['first_name'];
    if (!empty($p['middle_name'])) $name .= ' ' . $p['middle_name'];
    $name .= ' ' . $p['last_name'];
    if (!empty($p['suffix'])) $name .= ' ' . $p['suffix'];
    return $name;
}

function age_of(string $birthDate): int
{
    return (new DateTime($birthDate))->diff(new DateTime('today'))->y;
}

function full_address(array $p): string
{
    $parts = [$p['street']];
    if (!empty($p['zone']))     $parts[] = 'Zone ' . $p['zone'];
    $parts[] = $p['barangay'];
    if (!empty($p['district'])) $parts[] = $p['district'];
    $parts[] = $p['municipality'];
    $parts[] = $p['province'];
    $parts[] = $p['region'];
    return implode(', ', array_filter($parts));
}

function self_record(int $userId): ?array
{
    return q('SELECT * FROM patients WHERE user_id = ? AND is_self = 1 AND is_deleted = 0 LIMIT 1', [$userId])->fetch() ?: null;
}

function dependents_of(int $userId): array
{
    return q('SELECT * FROM patients WHERE user_id = ? AND is_self = 0 AND is_deleted = 0 ORDER BY first_name, last_name', [$userId])->fetchAll();
}

function all_slots(): array
{
    static $slots = null;
    if ($slots !== null) return $slots;
    $slots = [];
    $t   = strtotime('2000-01-01 ' . CLINIC_OPEN);
    $end = strtotime('2000-01-01 ' . CLINIC_CLOSE);
    $i   = 0;
    while ($t + SLOT_MINUTES * 60 <= $end) {
        $slots[] = [
            'start' => date('H:i', $t),
            'end'   => date('H:i', $t + SLOT_MINUTES * 60),
            'type'  => ($i % 2 === 0) ? 'booking' : 'walkin',
            'pos'   => $i + 1,
        ];
        $t += SLOT_MINUTES * 60;
        $i++;
    }
    return $slots;
}

function find_booking_slot(string $time): ?array
{
    $time = substr($time, 0, 5);
    foreach (all_slots() as $s) {
        if ($s['start'] === $time && $s['type'] === 'booking') return $s;
    }
    return null;
}

function slot_label(string $time): string
{
    $start = strtotime('2000-01-01 ' . substr($time, 0, 5));
    return date('g:i A', $start) . ' – ' . date('g:i A', $start + SLOT_MINUTES * 60);
}

function nice_date(string $date): string
{
    return date('D, M j, Y', strtotime($date));
}

function make_queue_no(string $date, string $time): string
{
    $slot = find_booking_slot($time);
    return date('ymd', strtotime($date)) . '-' . str_pad((string)($slot['pos'] ?? 0), 2, '0', STR_PAD_LEFT);
}

function short_queue(string $queueNo): string
{
    $parts = explode('-', $queueNo);
    return end($parts);
}

function first_booking_date(): string
{
    $last = '';
    foreach (all_slots() as $s) if ($s['type'] === 'booking') $last = $s['start'];
    return date('H:i') >= $last ? date('Y-m-d', strtotime('+1 day')) : date('Y-m-d');
}

function last_booking_date(): string
{
    return date('Y-m-d', strtotime('+' . BOOKING_DAYS_AHEAD . ' days'));
}

function slots_for_date(string $date): array
{
    $taken = [];
    $rows = q("SELECT slot_time AS t FROM appointments WHERE appt_date = ? AND status = 'upcoming'
               UNION
               SELECT offer_time AS t FROM appointments WHERE offer_date = ? AND offer_status = 'pending'",
        [$date, $date])->fetchAll();
    foreach ($rows as $r) $taken[substr($r['t'], 0, 5)] = true;

    $isToday = ($date === date('Y-m-d'));
    $now = date('H:i');
    $out = [];
    foreach (all_slots() as $s) {
        if ($s['type'] !== 'booking') continue;
        $available = empty($taken[$s['start']]) && !($isToday && $s['start'] <= $now);
        $out[] = ['time' => $s['start'], 'label' => slot_label($s['start']), 'available' => $available];
    }
    return $out;
}

function month_availability(string $ym): array
{
    $out = [];
    $days = (int)date('t', strtotime($ym . '-01'));
    for ($d = 1; $d <= $days; $d++) {
        $date = sprintf('%s-%02d', $ym, $d);
        if (booking_date_error($date) !== null) continue;
        $out[$date] = count(array_filter(slots_for_date($date), fn($s) => $s['available']));
    }
    return $out;
}

function slot_is_available(string $date, string $time): bool
{
    foreach (slots_for_date($date) as $s) {
        if ($s['time'] === substr($time, 0, 5)) return $s['available'];
    }
    return false;
}

function with_booking_lock(callable $fn)
{
    $got = (int)q("SELECT GET_LOCK('b509_booking', 10)")->fetchColumn();
    if ($got !== 1) throw new RuntimeException('The system is busy. Please try again.');
    try {
        return $fn();
    } finally {
        q("SELECT RELEASE_LOCK('b509_booking')");
    }
}

function create_appointment(array $a): int
{
    return with_booking_lock(function () use ($a) {
        if (!slot_is_available($a['appt_date'], $a['slot_time'])) {
            throw new RuntimeException('Sorry, that time slot was just taken. Please choose another slot.');
        }
        $dup = q("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND appt_date = ? AND status = 'upcoming'",
            [$a['patient_id'], $a['appt_date']])->fetchColumn();
        if ($dup > 0) {
            throw new RuntimeException('This patient already has an upcoming appointment on that date.');
        }
        try {
            q('INSERT INTO appointments
                (queue_no, user_id, patient_id, booked_for, visit_type, service, appt_date, slot_time,
                 priority, notes, result_file, id_file, rescheduled_from)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                make_queue_no($a['appt_date'], $a['slot_time']),
                $a['user_id'], $a['patient_id'], $a['booked_for'], $a['visit_type'], $a['service'],
                $a['appt_date'], $a['slot_time'] . (strlen($a['slot_time']) === 5 ? ':00' : ''),
                $a['priority'], $a['notes'] ?: null, $a['result_file'] ?: null, $a['id_file'] ?: null,
                $a['rescheduled_from'] ?? null,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new RuntimeException('Sorry, that time slot was just taken. Please choose another slot.');
            }
            throw $e;
        }
        return (int)db()->lastInsertId();
    });
}

function mark_missed(): void
{
    if (!empty($GLOBALS['__missed_done'])) return;
    $GLOBALS['__missed_done'] = true;
    $today = date('Y-m-d');
    q("UPDATE appointments SET status = 'missed' WHERE status = 'upcoming' AND appt_date < ?", [$today]);
    q("UPDATE appointments SET offer_status = 'expired' WHERE offer_status = 'pending' AND offer_date < ?", [$today]);
}

function can_reschedule(array $appt): bool
{
    if ($appt['status'] !== 'cancelled') return false;
    $okReason = $appt['cancelled_by'] === 'patient'
        || in_array($appt['offer_status'], ['declined', 'expired'], true);
    if (!$okReason) return false;
    $already = q('SELECT COUNT(*) FROM appointments WHERE rescheduled_from = ?', [$appt['id']])->fetchColumn();
    return (int)$already === 0;
}

function notify(int $userId, string $message, ?string $link = null): void
{
    q('INSERT INTO notifications (user_id, message, link) VALUES (?,?,?)', [$userId, $message, $link]);
}

function unread_count(int $userId): int
{
    return (int)q('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId])->fetchColumn();
}

function send_sms(string $mobile, string $message): bool
{
    if (SMS_API_KEY === '' || !function_exists('curl_init')) return false;
    $fields = ['apikey' => SMS_API_KEY, 'number' => $mobile, 'message' => $message];
    if (SMS_SENDER_NAME !== '') $fields['sendername'] = SMS_SENDER_NAME;
    $ch = curl_init('https://api.semaphore.co/api/v4/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $res !== false && $code >= 200 && $code < 300;
}

/*
 * Uploaded files are stored inside the database (table "files"), because
 * Vercel does not keep files saved on its servers. Each file gets a random
 * name like 3f9a...c2.jpg that is used in links (file.php?f=...).
 */
function save_upload(string $field, array $allowed, array &$errors, ?int $ownerId = null): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        $errors[$field] = 'File is too large (max ' . MAX_UPLOAD_MB . ' MB).';
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        $errors[$field] = 'Upload failed. Please try again.';
        return null;
    }
    if ($f['size'] > MAX_UPLOAD_MB * 1024 * 1024) {
        $errors[$field] = 'File is too large (max ' . MAX_UPLOAD_MB . ' MB).';
        return null;
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $map  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    if (!isset($map[$mime]) || !in_array($map[$mime], $allowed, true)) {
        $errors[$field] = 'Invalid file type. Allowed: ' . strtoupper(implode(', ', $allowed)) . '.';
        return null;
    }
    $data = file_get_contents($f['tmp_name']);
    if ($data === false) {
        $errors[$field] = 'Could not read the file. Please try again.';
        return null;
    }
    $name = bin2hex(random_bytes(16)) . '.' . $map[$mime];
    try {
        $st = db()->prepare('INSERT INTO files (name, mime, size, user_id, data) VALUES (?,?,?,?,?)');
        $st->bindValue(1, $name);
        $st->bindValue(2, $mime);
        $st->bindValue(3, strlen($data), PDO::PARAM_INT);
        $st->bindValue(4, $ownerId ?? ($_SESSION['user_id'] ?? null), $ownerId || !empty($_SESSION['user_id']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(5, $data, PDO::PARAM_LOB);
        $st->execute();
    } catch (PDOException $e) {
        error_log('Upload save failed: ' . $e->getMessage());
        $errors[$field] = 'Could not save the file. Please try again.';
        return null;
    }
    return $name;
}

function delete_upload(?string $name): void
{
    if ($name && preg_match('/^[a-f0-9]{32}\.(jpg|png|pdf)$/', $name)) {
        q('DELETE FROM files WHERE name = ?', [$name]);
    }
}

/* ---------- Email (SMTP, e.g. Gmail with an App Password) ---------- */

function mail_ready(): bool
{
    return SMTP_USER !== '' && SMTP_PASS !== '';
}

function send_mail(string $to, string $subject, string $html, string $text): bool
{
    if (!mail_ready() || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $ctx  = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
    $remote = ($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { error_log("SMTP connect failed: $errstr"); return false; }
    stream_set_timeout($fp, 10);

    $read = function () use ($fp): string {
        $out = '';
        while (($line = fgets($fp, 515)) !== false) {
            $out .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $out;
    };
    // $label is what goes in the error log. Never log the login lines (they contain the password).
    $cmd = function (string $c, array $ok, string $label = '') use ($fp, $read): string {
        fwrite($fp, $c . "\r\n");
        $r = $read();
        if (!in_array((int)substr($r, 0, 3), $ok, true)) {
            throw new RuntimeException('SMTP error at ' . ($label !== '' ? $label : explode(' ', $c)[0]) . ': ' . trim($r));
        }
        return $r;
    };

    try {
        $read();
        $me = parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost';
        $ehlo = $cmd('EHLO ' . $me, [250]);
        if ($port !== 465 && stripos($ehlo, 'STARTTLS') !== false) {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('STARTTLS failed');
            $cmd('EHLO ' . $me, [250]);
        }
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode(SMTP_USER), [334], 'login (email address)');
        $cmd(base64_encode(SMTP_PASS), [235], 'login (App Password) - check SMTP_USER and SMTP_PASS');
        $cmd('MAIL FROM:<' . SMTP_USER . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);

        $boundary = 'b509_' . bin2hex(random_bytes(8));
        $enc = fn(string $s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
        $headers = [
            'Date: ' . date('r'),
            'From: ' . $enc(MAIL_FROM_NAME) . ' <' . SMTP_USER . '>',
            'To: <' . $to . '>',
            'Subject: ' . $enc($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $me . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $body = implode("\r\n", $headers) . "\r\n\r\n"
            . "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--$boundary--\r\n";
        fwrite($fp, $body . "\r\n.\r\n");
        $r = $read();
        if ((int)substr($r, 0, 3) !== 250) throw new RuntimeException('SMTP message rejected: ' . trim($r));
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        error_log($e->getMessage());
        @fclose($fp);
        return false;
    }
}

function absolute_url(string $path): string
{
    if (APP_URL !== '') return APP_URL . '/' . ltrim($path, '/');
    $https = APP_ENV === 'production' || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($path);
}

/* ---------- PhilHealth / YAKAP numbers and account approval ---------- */

const PHILHEALTH_PATTERN = '/^\d{2}-\d{9}-\d$/';

/** Accepts "123456789012" or "12-345678901-2" and returns "12-345678901-2", or null if invalid. */
function normalize_philhealth(string $v): ?string
{
    $digits = preg_replace('/\D/', '', $v);
    if (strlen($digits) !== 12) return null;
    return substr($digits, 0, 2) . '-' . substr($digits, 2, 9) . '-' . substr($digits, 11, 1);
}

/** True if another (non-deleted) patient already uses this PhilHealth number. */
function philhealth_taken(string $no, int $exceptPatientId = 0): bool
{
    return (int)q('SELECT COUNT(*) FROM patients WHERE philhealth_no = ? AND is_deleted = 0 AND id <> ?', [$no, $exceptPatientId])->fetchColumn() > 0;
}

/**
 * ID shown for a patient: their own PhilHealth/YAKAP number, or for a dependent
 * without one, the account holder's number + ".D" + dependent number (e.g. 12-345678901-2.D1).
 */
function patient_pid(array $p, ?string $holderNo = null): string
{
    if (!empty($p['philhealth_no'])) return $p['philhealth_no'];
    if (!empty($p['is_self'])) return '';
    if ($holderNo === null) {
        $holderNo = (string)q('SELECT philhealth_no FROM patients WHERE user_id = ? AND is_self = 1 LIMIT 1', [$p['user_id']])->fetchColumn();
    }
    return $holderNo !== '' ? $holderNo . '.D' . (int)$p['dep_seq'] : '';
}

function next_dep_seq(int $userId): int
{
    return (int)q('SELECT COALESCE(MAX(dep_seq), 0) + 1 FROM patients WHERE user_id = ? AND is_self = 0', [$userId])->fetchColumn();
}

const APPROVAL_LABELS = ['new' => 'Incomplete', 'pending' => 'Waiting for approval', 'verified' => 'Verified'];

function account_badge(array $u): string
{
    if (!(int)$u['is_active']) return '<span class="badge badge-cancelled"><i></i>Deactivated</span>';
    $map = ['new' => 'missed', 'pending' => 'upcoming', 'verified' => 'completed'];
    $st = $u['approval_status'];
    return '<span class="badge badge-' . ($map[$st] ?? 'missed') . '"><i></i>' . h(APPROVAL_LABELS[$st] ?? $st) . '</span>';
}

/** Patients can only book once staff have verified their PhilHealth details. */
function require_verified_patient(array $user): void
{
    if (($user['approval_status'] ?? '') === 'verified') return;
    if ($user['approval_status'] === 'new') {
        flash('info', 'Please complete your registration first. Staff will verify it before you can book.');
    } else {
        flash('info', 'Your account is still waiting for approval. You can book once the health center verifies your PhilHealth details.');
    }
    redirect('patient/profile.php');
}

function pending_accounts_count(): int
{
    return (int)q("SELECT COUNT(*) FROM users WHERE role = 'patient' AND approval_status = 'pending' AND is_active = 1")->fetchColumn();
}

function active_doctors(): array
{
    return q('SELECT id, name FROM doctors WHERE is_active = 1 ORDER BY name')->fetchAll();
}

function status_badge(string $status, ?string $by = null): string
{
    $labels = ['upcoming' => 'Upcoming', 'completed' => 'Completed', 'missed' => 'Missed', 'cancelled' => 'Cancelled'];
    $text = $labels[$status] ?? ucfirst($status);
    if ($status === 'cancelled' && $by) $text .= $by === 'staff' ? ' by clinic' : ' by patient';
    return '<span class="badge badge-' . h($status) . '"><i></i>' . h($text) . '</span>';
}

function priority_chip(string $priority): string
{
    $cls = ['Regular' => 'regular', 'Senior Citizen' => 'senior', 'PWD' => 'pwd', 'Pregnant' => 'pregnant'][$priority] ?? 'regular';
    return '<span class="chip chip-' . $cls . '">' . h($priority) . '</span>';
}

function field_error(array $errors, string $key): string
{
    return '<small class="field-error">' . (isset($errors[$key]) ? h($errors[$key]) : '') . '</small>';
}

function invalid_class(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' invalid' : '';
}

function select_options(array $options, string $selected, bool $placeholder = true): string
{
    $html = $placeholder ? '<option value="">-- Select --</option>' : '';
    foreach ($options as $value => $label) {
        if (is_int($value)) $value = $label;
        $sel = ((string)$value === $selected) ? ' selected' : '';
        $html .= '<option value="' . h($value) . '"' . $sel . '>' . h($label) . '</option>';
    }
    return $html;
}
