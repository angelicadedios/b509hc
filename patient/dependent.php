<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/patient_fields.php';
$user = require_role('patient');
$uid = (int)$user['id'];

$id = (int)($_GET['id'] ?? 0);
$dep = null;
if ($id) {
    $dep = q('SELECT * FROM patients WHERE id = ? AND user_id = ? AND is_self = 0 AND is_deleted = 0', [$id, $uid])->fetch();
    if (!$dep) {
        flash('error', 'Dependent not found.');
        redirect('patient/profile.php');
    }
}
$values = $dep ?? [];
$errors = [];

if (is_post()) {
    verify_csrf();
    [$data, $errors] = validate_patient($_POST, true);
    [$data['philhealth_no'], $phErr] = validate_philhealth(input('philhealth_no'), false, (int)($dep['id'] ?? 0));
    if ($phErr) $errors['philhealth_no'] = $phErr;
    $data['dep_seq'] = $dep['dep_seq'] ?? null;
    $values = $data;
    if (!$errors) {
        $cols = [$data['philhealth_no'] ?: null, $data['relationship'], $data['first_name'], $data['middle_name'] ?: null, $data['last_name'], $data['suffix'] ?: null,
                 $data['sex'], $data['birth_date'], $data['mobile'], $data['region'], $data['province'],
                 $data['municipality'], $data['barangay'], $data['district'] ?: null, $data['zone'] ?: null, $data['street']];
        if ($dep) {
            q('UPDATE patients SET philhealth_no=?, relationship=?, first_name=?, middle_name=?, last_name=?, suffix=?, sex=?, birth_date=?, mobile=?,
               region=?, province=?, municipality=?, barangay=?, district=?, zone=?, street=? WHERE id=? AND user_id=?',
               array_merge($cols, [$dep['id'], $uid]));
            flash('success', 'Dependent updated.');
        } else {
            q('INSERT INTO patients (philhealth_no, relationship, first_name, middle_name, last_name, suffix, sex, birth_date, mobile,
               region, province, municipality, barangay, district, zone, street, user_id, dep_seq, is_self)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)', array_merge($cols, [$uid, next_dep_seq($uid)]));
            flash('success', 'Dependent added.');
        }
        redirect('patient/profile.php');
    }
    flash('error', 'Please fix the highlighted fields.');
}

$pageTitle = $dep ? 'Edit dependent' : 'Add dependent';
$activeNav = 'profile';
$pageScripts = ['address.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <a class="back" href="<?= h(url('patient/profile.php')) ?>">&larr; Profile</a>
    <h1><?= h($pageTitle) ?></h1>
</div>
<form method="post" class="card" data-validate-form novalidate>
    <?= csrf_field() ?>
    <?php render_patient_fields($values, $errors, true, '', 'optional'); ?>
    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= h(url('patient/profile.php')) ?>">Cancel</a>
        <button type="submit" class="btn btn-primary"><?= $dep ? 'Save changes' : 'Add dependent' ?></button>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
