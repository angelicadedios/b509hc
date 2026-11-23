<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/patient_fields.php';
$user = require_role('patient');
$uid = (int)$user['id'];

$self = self_record($uid);
$values = $self ?? [];
$errors = [];

if (is_post()) {
    verify_csrf();
    $action = input('action');

    if ($action === 'save_self') {
        $verified = $user['approval_status'] === 'verified';
        [$data, $errors] = validate_patient($_POST, false);
        $data['philhealth_no']   = $self['philhealth_no'] ?? '';
        $data['philhealth_file'] = $self['philhealth_file'] ?? '';
        $newFile = null;
        if (!$verified) {
            [$data['philhealth_no'], $phErr] = validate_philhealth(input('philhealth_no'), true, (int)($self['id'] ?? 0));
            if ($phErr) $errors['philhealth_no'] = $phErr;
            if (!$errors) {
                $newFile = save_upload('philhealth_file', ['jpg', 'png', 'pdf'], $errors, $uid);
                if (!$newFile && empty($self['philhealth_file']) && empty($errors['philhealth_file'])) {
                    $errors['philhealth_file'] = 'Please upload a picture of your PhilHealth ID.';
                }
            }
        }
        $values = $data;
        if (!$errors) {
            $cols = [$data['first_name'], $data['middle_name'] ?: null, $data['last_name'], $data['suffix'] ?: null,
                     $data['sex'], $data['birth_date'], $data['mobile'], $data['region'], $data['province'],
                     $data['municipality'], $data['barangay'], $data['district'] ?: null, $data['zone'] ?: null, $data['street'],
                     $data['philhealth_no'], $newFile ?: ($self['philhealth_file'] ?? null)];
            if ($self) {
                q('UPDATE patients SET first_name=?, middle_name=?, last_name=?, suffix=?, sex=?, birth_date=?, mobile=?,
                   region=?, province=?, municipality=?, barangay=?, district=?, zone=?, street=?, philhealth_no=?, philhealth_file=?
                   WHERE id=? AND user_id=?',
                   array_merge($cols, [$self['id'], $uid]));
                if ($newFile && !empty($self['philhealth_file'])) delete_upload($self['philhealth_file']);
            } else {
                q('INSERT INTO patients (first_name, middle_name, last_name, suffix, sex, birth_date, mobile,
                   region, province, municipality, barangay, district, zone, street, philhealth_no, philhealth_file, user_id, is_self)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)', array_merge($cols, [$uid]));
            }
            if ($user['approval_status'] === 'new') {
                q("UPDATE users SET approval_status = 'pending', submitted_at = NOW(), review_note = NULL WHERE id = ?", [$uid]);
                flash('success', 'Registration sent! The health center will check your PhilHealth details. We will email you at '
                    . $user['email'] . ' once your account is approved.');
            } else {
                flash('success', 'Your profile has been saved.');
            }
            redirect('patient/profile.php');
        }
        delete_upload($newFile);
        flash('error', 'Please fix the highlighted fields.' . (isset($_FILES['philhealth_file']) && empty($errors['philhealth_file']) && !$verified ? ' If you chose a file, please choose it again.' : ''));
    }

    if ($action === 'delete_dependent') {
        $id = (int)input('id');
        $dep = q('SELECT * FROM patients WHERE id = ? AND user_id = ? AND is_self = 0 AND is_deleted = 0', [$id, $uid])->fetch();
        if (!$dep) {
            flash('error', 'Dependent not found.');
        } else {
            $active = q("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status = 'upcoming'", [$id])->fetchColumn();
            if ($active > 0) {
                flash('error', full_name($dep) . ' still has an upcoming appointment. Cancel it first before deleting.');
            } else {

                q('UPDATE patients SET is_deleted = 1 WHERE id = ?', [$id]);
                flash('success', full_name($dep) . ' was removed from your dependents.');
            }
        }
        redirect('patient/profile.php');
    }
}

$dependents = dependents_of($uid);
$status = $user['approval_status'];
$pageTitle = $status === 'new' ? 'Complete registration' : 'Profile';
$activeNav = 'profile';
$pageScripts = ['address.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1><?= $status === 'new' ? 'Complete your registration' : 'Profile' ?></h1>
    <p><?= $status === 'new'
        ? 'Fill in your details and upload your PhilHealth ID. The health center will verify your account before you can book.'
        : 'Your details as the account holder. They\'re filled in automatically when you book for yourself.' ?></p>
</div>

<?php if ($status === 'new' && !empty($user['review_note'])): ?>
    <div class="alert alert-error"><strong>Please update your registration.</strong> Note from the health center: <?= h($user['review_note']) ?></div>
<?php endif; ?>
<?php if ($status === 'pending'): ?>
    <div class="alert alert-warning"><strong>Waiting for approval.</strong> The health center is checking your PhilHealth details and residency.
        We'll email <?= h($user['email']) ?> once you can book. You can still add your dependents below.</div>
<?php elseif ($status === 'verified'): ?>
    <div class="alert alert-success" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <span><strong>Verified account.</strong> You can book appointments.</span>
        <a class="btn btn-sm btn-primary" href="<?= h(url('patient/book.php')) ?>">Book now</a>
    </div>
<?php endif; ?>

<form method="post" class="card" enctype="multipart/form-data" data-validate-form novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_self">
    <?php render_patient_fields($values, $errors, false, $user['email'], $status === 'verified' ? 'locked' : 'register'); ?>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $status === 'new' ? 'Submit registration' : 'Save profile' ?></button>
    </div>
</form>

<div class="card">
    <div class="card-head">
        <div>
            <h2>Dependents</h2>
            <p class="muted small">Family members or other people you book for.</p>
        </div>
        <a class="btn btn-ghost" href="<?= h(url('patient/dependent.php')) ?>">Add dependent</a>
    </div>
    <?php if (!$dependents): ?>
        <p class="empty">No dependents yet. Add one here, or while booking for someone else.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Name</th><th>PhilHealth / ID no.</th><th>Relationship</th><th>Sex</th><th>Age</th><th>Mobile</th><th class="right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($dependents as $d): ?>
                <tr>
                    <td><?= h(full_name($d)) ?></td>
                    <td class="mono small"><?= h(patient_pid($d, $self['philhealth_no'] ?? '')) ?: '<span class="muted">-</span>' ?></td>
                    <td><?= h($d['relationship']) ?></td>
                    <td><?= h($d['sex']) ?></td>
                    <td><?= age_of($d['birth_date']) ?></td>
                    <td><?= h($d['mobile']) ?></td>
                    <td class="right nowrap">
                        <a class="btn btn-sm btn-ghost" href="<?= h(url('patient/dependent.php?id=' . $d['id'])) ?>">Edit</a>
                        <button type="button" class="btn btn-sm btn-danger-ghost"
                                data-open="deleteModal" data-fill='<?= h(json_encode(['id' => $d['id'], 'name' => full_name($d)])) ?>'>Remove</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="modal" id="deleteModal" hidden>
    <div class="modal-box">
        <h3>Remove dependent?</h3>
        <p class="muted">Remove <strong data-slot="name"></strong> from your dependents? Their past appointments stay in your history.</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_dependent">
            <input type="hidden" name="id" data-slot="id">
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" data-close>Cancel</button>
                <button type="submit" class="btn btn-danger">Remove</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
