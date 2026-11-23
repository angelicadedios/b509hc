<?php
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) redirect('login.php');
verify_csrf();

$credential = $_POST['credential'] ?? '';
if (!is_string($credential) || !preg_match('/^[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+$/', $credential)) {
    flash('error', 'Google sign-in failed. Please try again.');
    redirect('login.php');
}

function google_tokeninfo(string $idToken): ?array
{
    $endpoint = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $body = @file_get_contents($endpoint);
        $code = $body === false ? 0 : 200;
    }
    if ($body === false || $code !== 200) return null;
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

$info = google_tokeninfo($credential);

$valid = $info
    && ($info['aud'] ?? '') === GOOGLE_CLIENT_ID
    && in_array($info['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
    && (int)($info['exp'] ?? 0) > time()
    && in_array($info['email_verified'] ?? '', ['true', true], true)
    && !empty($info['sub'])
    && filter_var($info['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$valid) {
    flash('error', 'We could not verify your Google account. Please try again.');
    redirect('login.php');
}

$sub   = (string)$info['sub'];
$email = strtolower((string)$info['email']);
$name  = mb_substr(trim((string)($info['name'] ?? '')), 0, 150);

$user = q('SELECT * FROM users WHERE google_sub = ?', [$sub])->fetch();
if (!$user) {
    $user = q('SELECT * FROM users WHERE email = ?', [$email])->fetch();
    if ($user && $user['role'] !== 'patient') {
        flash('error', 'This email is registered as a staff account. Please use the Staff login.');
        redirect('staff_login.php');
    }
    if ($user) {
        q('UPDATE users SET google_sub = ?, display_name = ? WHERE id = ?', [$sub, $name, $user['id']]);
    } else {
        q("INSERT INTO users (role, email, google_sub, display_name) VALUES ('patient', ?, ?, ?)", [$email, $sub, $name]);
        flash('success', 'Welcome! Your account has been created. Please complete your registration below.');
    }
    $user = q('SELECT * FROM users WHERE google_sub = ?', [$sub])->fetch();
}

if (!(int)$user['is_active']) {
    flash('error', 'This account has been deactivated by the health center'
        . ($user['deactivated_reason'] ? ' (Reason: ' . $user['deactivated_reason'] . ')' : '')
        . '. Please visit or contact the health center.');
    redirect('login.php');
}

login_user($user);
if ($user['approval_status'] === 'new') {
    flash('info', 'Please complete your registration. Staff will check your PhilHealth details before you can book.');
    redirect('patient/profile.php');
}
redirect($user['approval_status'] === 'verified' ? 'patient/bookings.php' : 'patient/profile.php');
