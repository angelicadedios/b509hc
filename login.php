<?php
require __DIR__ . '/includes/bootstrap.php';

$u = current_user();
if ($u) redirect($u['role'] === 'staff' ? 'staff/index.php' : 'patient/bookings.php');

$devAllowed = dev_login_allowed();
$devErrors = [];

if (is_post() && input('action') === 'dev' && $devAllowed) {
    verify_csrf();
    $email = strtolower(input('email'));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $devErrors['email'] = 'Enter a valid email address.';
    } else {
        $user = q('SELECT * FROM users WHERE email = ?', [$email])->fetch();
        if ($user && $user['role'] !== 'patient') {
            $devErrors['email'] = 'That email belongs to a staff account.';
        } else {
            if (!$user) {
                q("INSERT INTO users (role, email, display_name) VALUES ('patient', ?, ?)", [$email, strstr($email, '@', true)]);
                $user = q('SELECT * FROM users WHERE email = ?', [$email])->fetch();
            }
            if (!(int)$user['is_active']) {
                $devErrors['email'] = 'This account has been deactivated by the health center.';
            } else {
                login_user($user);
                redirect($user['approval_status'] === 'verified' ? 'patient/bookings.php' : 'patient/profile.php');
            }
        }
    }
}

$pageTitle = 'Sign in';
$mainClass = 'auth-main';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/signin_card.php';
?>
<section class="hero-band auth-band">
    <div class="container">
        <div class="auth-center">
            <?php render_signin_card('patient', $devErrors); ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
