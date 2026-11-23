<?php
require __DIR__ . '/includes/bootstrap.php';

$u = current_user();
if ($u) redirect($u['role'] === 'staff' ? 'staff/index.php' : 'patient/bookings.php');

$errors = [];
$email = '';

$lockedUntil = $_SESSION['staff_lock_until'] ?? 0;

if (is_post()) {
    verify_csrf();
    $email = strtolower(input('email'));
    $password = $_POST['password'] ?? '';

    if (time() < $lockedUntil) {
        $errors['form'] = 'Too many failed attempts. Please wait ' . ceil(($lockedUntil - time()) / 60) . ' minute(s).';
    } else {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
        if (!is_string($password) || $password === '') $errors['password'] = 'Password is required.';

        if (!$errors) {
            $user = q("SELECT * FROM users WHERE email = ? AND role = 'staff'", [$email])->fetch();
            if ($user && password_verify($password, (string)$user['password_hash']) && !(int)$user['is_active']) {
                $errors['form'] = 'This account has been disabled. Ask an administrator to enable it.';
            } elseif ($user && password_verify($password, (string)$user['password_hash'])) {
                unset($_SESSION['staff_fail'], $_SESSION['staff_lock_until']);
                login_user($user);
                redirect('staff/index.php');
            }
            if (empty($errors['form'])) {
                $_SESSION['staff_fail'] = ($_SESSION['staff_fail'] ?? 0) + 1;
                if ($_SESSION['staff_fail'] >= 5) {
                    $_SESSION['staff_lock_until'] = time() + 300;
                    $_SESSION['staff_fail'] = 0;
                }
                $errors['form'] = 'Incorrect email or password.';
            }
        }
    }
}

$pageTitle = 'Staff sign-in';
$mainClass = 'auth-main';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/signin_card.php';
?>
<section class="hero-band auth-band">
    <div class="container">
        <div class="auth-center">
            <?php render_signin_card('staff', [], $errors, $email); ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
