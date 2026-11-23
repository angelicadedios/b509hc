<?php
require __DIR__ . '/../includes/bootstrap.php';
$staff = require_role('staff');
$errors = [];

if (is_post()) {
    verify_csrf();
    $current = $_POST['current'] ?? '';
    $new     = $_POST['new'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $hash = q('SELECT password_hash FROM users WHERE id = ?', [$staff['id']])->fetchColumn();

    if (!is_string($current) || !password_verify($current, (string)$hash)) $errors['current'] = 'Current password is incorrect.';
    if (!is_string($new) || ($e = password_error($new))) {
        $errors['new'] = $e ?? 'Please enter a new password.';
    }
    if ($new !== $confirm) $errors['confirm'] = 'Passwords do not match.';

    if (!$errors) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $staff['id']]);
        session_regenerate_id(true);
        flash('success', 'Password changed.');
        redirect('staff/password.php');
    }
}

$pageTitle = 'Change password';
$activeNav = 'password';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap">
<form method="post" class="card auth-card" data-validate-form novalidate>
    <img class="brand-logo" src="<?= h(url('assets/img/logo.png')) ?>" alt="" width="48" height="48" style="width:48px;height:48px;margin-bottom:16px">
    <h1>Change password</h1>
    <p class="muted small">Logged in as <?= h($staff['email']) ?></p>
    <?= csrf_field() ?>
    <div class="field">
        <label for="current">Current password</label>
        <input type="password" id="current" name="current" autocomplete="current-password" data-validate="required" class="<?= invalid_class($errors, 'current') ?>">
        <?= field_error($errors, 'current') ?>
    </div>
    <div class="field">
        <label for="new">New password</label>
        <input type="password" id="new" name="new" autocomplete="new-password" maxlength="72" data-validate="required|password" class="<?= invalid_class($errors, 'new') ?>">
        <?= field_error($errors, 'new') ?>
    </div>
    <div class="field">
        <label for="confirm">Confirm new password</label>
        <input type="password" id="confirm" name="confirm" autocomplete="new-password" data-validate="required|same:new" class="<?= invalid_class($errors, 'confirm') ?>">
        <?= field_error($errors, 'confirm') ?>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Change password</button>
</form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
