<?php
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();
$adminId = (int)$admin['id'];

$addErrors = [];
$addVals = ['display_name' => '', 'email' => '', 'password' => '', 'is_admin' => ''];

function staff_by_id(int $id): ?array
{
    return q("SELECT * FROM users WHERE id = ? AND role = 'staff'", [$id])->fetch() ?: null;
}

if (is_post()) {
    verify_csrf();
    $action = input('action');

    if ($action === 'add') {
        $addVals = [
            'display_name' => preg_replace('/\s+/', ' ', input('display_name')),
            'email'        => strtolower(input('email')),
            'password'     => (string)($_POST['password'] ?? ''),
            'is_admin'     => input('is_admin') === '1' ? '1' : '',
        ];
        if ($addVals['display_name'] === '') $addErrors['display_name'] = 'Name is required.';
        elseif (!preg_match("/^[\\p{L} .'\\-]{2,100}$/u", $addVals['display_name'])) $addErrors['display_name'] = "Use letters, spaces, . ' - only (2-100 characters).";

        if (!filter_var($addVals['email'], FILTER_VALIDATE_EMAIL) || strlen($addVals['email']) > 190) {
            $addErrors['email'] = 'Enter a valid email address.';
        } else {
            $existing = q('SELECT role, is_active FROM users WHERE email = ?', [$addVals['email']])->fetch();
            if ($existing && $existing['role'] === 'patient') $addErrors['email'] = 'This email already belongs to a patient account. Use a different email.';
            elseif ($existing && !(int)$existing['is_active']) $addErrors['email'] = 'This staff account exists but is disabled. Enable it from the list instead.';
            elseif ($existing) $addErrors['email'] = 'This email already has a staff account.';
        }

        if ($e = password_error($addVals['password'])) $addErrors['password'] = $e;

        if (!$addErrors) {
            q("INSERT INTO users (role, is_admin, is_active, email, password_hash, display_name) VALUES ('staff', ?, 1, ?, ?, ?)", [
                $addVals['is_admin'] === '1' ? 1 : 0,
                $addVals['email'],
                password_hash($addVals['password'], PASSWORD_DEFAULT),
                $addVals['display_name'],
            ]);
            flash('success', $addVals['display_name'] . ' can now sign in with ' . $addVals['email'] . '. Share the temporary password privately and ask them to change it.');
            redirect('staff/manage.php');
        }
    } else {
        $target = staff_by_id((int)input('id'));
        if (!$target) {
            flash('error', 'Staff account not found.');
            redirect('staff/manage.php');
        }
        $name = $target['display_name'] !== '' ? $target['display_name'] : $target['email'];
        $isSelf = (int)$target['id'] === $adminId;
        $isLastAdmin = (int)$target['is_admin'] && (int)$target['is_active'] && active_admin_count() <= 1;

        if ($action === 'reset') {
            $pw = (string)($_POST['password'] ?? '');
            if ($e = password_error($pw)) {
                flash('error', 'Password not changed. ' . $e);
            } else {
                q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $target['id']]);
                flash('success', 'Password reset for ' . $name . '. Share the temporary password privately.');
            }
        }

        if ($action === 'toggle_admin') {
            if ($isSelf) flash('error', "You can't change your own administrator access.");
            elseif ($isLastAdmin) flash('error', 'There must be at least one active administrator.');
            else {
                $new = (int)$target['is_admin'] ? 0 : 1;
                q('UPDATE users SET is_admin = ? WHERE id = ?', [$new, $target['id']]);
                flash('success', $new ? $name . ' is now an administrator.' : $name . ' is no longer an administrator.');
            }
        }

        if ($action === 'toggle_active') {
            if ($isSelf) flash('error', "You can't disable your own account.");
            elseif ($isLastAdmin) flash('error', 'There must be at least one active administrator.');
            else {
                $new = (int)$target['is_active'] ? 0 : 1;
                q('UPDATE users SET is_active = ? WHERE id = ?', [$new, $target['id']]);
                flash('success', $new ? $name . ' can sign in again.' : $name . ' can no longer sign in.');
            }
        }
        redirect('staff/manage.php');
    }
}

$staffList = q("SELECT id, email, display_name, is_admin, is_active, created_at FROM users
                WHERE role = 'staff' ORDER BY is_active DESC, is_admin DESC, display_name, email")->fetchAll();

$pageTitle = 'Staff accounts';
$activeNav = 'staff';
$pageScripts = ['manage.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head row-between">
    <div>
        <h1>Staff accounts</h1>
        <p>People who can sign in to the staff panel. Administrators can also manage this list.</p>
    </div>
    <button type="button" class="btn btn-primary" data-open="addModal">Add staff</button>
</div>

<div class="card">
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>Name</th><th>Email</th><th>Access</th><th>Status</th><th>Added</th><th class="right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($staffList as $s):
            $self = (int)$s['id'] === $adminId;
            $sName = $s['display_name'] !== '' ? $s['display_name'] : $s['email']; ?>
        <tr>
            <td><strong style="font-weight:600;color:var(--ink)"><?= h($sName) ?></strong><?php if ($self): ?> <span class="badge badge-priority">You</span><?php endif; ?></td>
            <td><?= h($s['email']) ?></td>
            <td><?= (int)$s['is_admin'] ? '<span class="badge badge-upcoming"><i></i>Administrator</span>' : '<span class="badge"><i></i>Staff</span>' ?></td>
            <td><?= (int)$s['is_active'] ? '<span class="badge badge-completed"><i></i>Active</span>' : '<span class="badge badge-cancelled"><i></i>Disabled</span>' ?></td>
            <td class="nowrap muted"><?= h(date('M j, Y', strtotime($s['created_at']))) ?></td>
            <td class="right nowrap">
                <button type="button" class="btn btn-sm btn-ghost" data-open="resetModal"
                        data-fill='<?= h(json_encode(['id' => $s['id'], 'name' => $sName])) ?>'>Reset password</button>
                <?php if (!$self): ?>
                    <button type="button" class="btn btn-sm btn-ghost" data-open="actionModal"
                            data-fill='<?= h(json_encode((int)$s['is_admin']
                                ? ['id' => $s['id'], 'action' => 'toggle_admin', 'title' => 'Remove administrator access?', 'msg' => $sName . ' will still be able to use the staff panel, but not manage staff accounts.', 'btn' => 'Remove admin access']
                                : ['id' => $s['id'], 'action' => 'toggle_admin', 'title' => 'Make ' . $sName . ' an administrator?', 'msg' => 'Administrators can add staff, reset passwords and disable accounts.', 'btn' => 'Make administrator'])) ?>'>
                        <?= (int)$s['is_admin'] ? 'Remove admin' : 'Make admin' ?></button>
                    <button type="button" class="btn btn-sm <?= (int)$s['is_active'] ? 'btn-danger-ghost' : 'btn-ghost' ?>" data-open="actionModal"
                            data-fill='<?= h(json_encode((int)$s['is_active']
                                ? ['id' => $s['id'], 'action' => 'toggle_active', 'title' => 'Disable ' . $sName . '?', 'msg' => 'They will be signed out and won\'t be able to sign in. Their past records are kept, and you can enable the account again later.', 'btn' => 'Disable account']
                                : ['id' => $s['id'], 'action' => 'toggle_active', 'title' => 'Enable ' . $sName . '?', 'msg' => 'They will be able to sign in again with their current password.', 'btn' => 'Enable account'])) ?>'>
                        <?= (int)$s['is_active'] ? 'Disable' : 'Enable' ?></button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="modal" id="addModal" <?= $addErrors ? 'data-autoopen' : 'hidden' ?>>
    <div class="modal-box">
        <h3>Add staff</h3>
        <p class="muted small">They sign in at the staff sign-in page with this email and temporary password.</p>
        <form method="post" data-validate-form novalidate autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="field">
                <label for="display_name">Full name <span class="req">*</span></label>
                <input type="text" id="display_name" name="display_name" maxlength="100" value="<?= h($addVals['display_name']) ?>"
                       placeholder="e.g. Dr. Ana Santos" data-validate="required|min:2|max:100" class="<?= invalid_class($addErrors, 'display_name') ?>">
                <?= field_error($addErrors, 'display_name') ?>
            </div>
            <div class="field">
                <label for="new_email">Email <span class="req">*</span></label>
                <input type="email" id="new_email" name="email" maxlength="190" value="<?= h($addVals['email']) ?>"
                       placeholder="name@example.com" data-validate="required|email" class="<?= invalid_class($addErrors, 'email') ?>">
                <?= field_error($addErrors, 'email') ?>
            </div>
            <div class="field">
                <label for="new_password">Temporary password <span class="req">*</span></label>
                <div class="input-row">
                    <input type="text" id="new_password" name="password" maxlength="72" autocomplete="off" spellcheck="false"
                           data-validate="required|password" class="<?= invalid_class($addErrors, 'password') ?>">
                    <button type="button" class="btn btn-ghost" data-generate="new_password">Generate</button>
                </div>
                <?= field_error($addErrors, 'password') ?>
            </div>
            <label class="check-row">
                <input type="checkbox" name="is_admin" value="1" <?= $addVals['is_admin'] === '1' ? 'checked' : '' ?>>
                <span><strong>Administrator</strong><small>Can add staff, reset passwords and disable accounts.</small></span>
            </label>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" data-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Add staff</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="resetModal" hidden>
    <div class="modal-box">
        <h3>Reset password for <span data-slot="name"></span></h3>
        <p class="muted small">Share the new temporary password privately and ask them to change it after signing in.</p>
        <form method="post" data-validate-form novalidate autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset">
            <input type="hidden" name="id" data-slot="id">
            <div class="field">
                <label for="reset_password">New temporary password <span class="req">*</span></label>
                <div class="input-row">
                    <input type="text" id="reset_password" name="password" maxlength="72" autocomplete="off" spellcheck="false" data-validate="required|password">
                    <button type="button" class="btn btn-ghost" data-generate="reset_password">Generate</button>
                </div>
                <small class="field-error"></small>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" data-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Reset password</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="actionModal" hidden>
    <div class="modal-box">
        <h3 data-slot="title"></h3>
        <p class="muted" data-slot="msg"></p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" data-slot="action">
            <input type="hidden" name="id" data-slot="id">
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" data-close>Cancel</button>
                <button type="submit" class="btn btn-primary" data-slot="btn"></button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
