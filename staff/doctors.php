<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$errors = [];
$name = '';

if (is_post()) {
    verify_csrf();
    $action = input('action');

    if ($action === 'add') {
        $name = preg_replace('/\s+/', ' ', input('name'));
        if (!preg_match("/^[\\p{L} .,'\\-]{3,120}$/u", $name)) {
            $errors['name'] = "Use letters, spaces, . , ' - only (3-120 characters).";
        } elseif (q('SELECT COUNT(*) FROM doctors WHERE name = ?', [$name])->fetchColumn() > 0) {
            $errors['name'] = 'This doctor is already in the list.';
        }
        if (!$errors) {
            q('INSERT INTO doctors (name) VALUES (?)', [$name]);
            flash('success', $name . ' added. They now appear in the Doctor dropdown.');
            redirect('staff/doctors.php');
        }
    }

    if ($action === 'toggle') {
        $doc = q('SELECT * FROM doctors WHERE id = ?', [(int)input('id')])->fetch();
        if ($doc) {
            q('UPDATE doctors SET is_active = 1 - is_active WHERE id = ?', [$doc['id']]);
            flash('success', $doc['name'] . ($doc['is_active'] ? ' was hidden from the dropdown. Past records keep their name.' : ' is back in the dropdown.'));
        }
        redirect('staff/doctors.php');
    }
}

$doctors = q('SELECT d.*, (SELECT COUNT(*) FROM dispensations x WHERE x.doctor_id = d.id) AS uses FROM doctors d ORDER BY d.is_active DESC, d.name')->fetchAll();

$pageTitle = 'Doctors';
$activeNav = 'doctors';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Doctors</h1><p>These names appear in the <strong>Doctor</strong> dropdown when staff record the medicine given.</p></div>

<div class="grid grid-2 align-start">
    <div class="card">
        <h2>Doctor list</h2>
        <?php if (!$doctors): ?><p class="empty">No doctors yet.</p><?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Name</th><th>Status</th><th class="right">Action</th></tr></thead>
            <tbody>
            <?php foreach ($doctors as $d): ?>
                <tr>
                    <td><?= h($d['name']) ?><?php if ($d['uses']): ?><div class="small muted"><?= (int)$d['uses'] ?> prescription record(s)</div><?php endif; ?></td>
                    <td><?= $d['is_active'] ? '<span class="badge badge-completed"><i></i>Active</span>' : '<span class="badge badge-missed"><i></i>Hidden</span>' ?></td>
                    <td class="right">
                        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $d['is_active'] ? 'btn-danger-ghost' : 'btn-ghost' ?>"><?= $d['is_active'] ? 'Hide' : 'Show' ?></button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2>Add doctor</h2>
        <form method="post" data-validate-form novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="field">
                <label for="name">Full name <span class="req">*</span></label>
                <input type="text" id="name" name="name" maxlength="120" value="<?= h($name) ?>" placeholder="e.g. Dra. Ana Santos"
                       data-validate="required|min:3|max:120" class="<?= invalid_class($errors, 'name') ?>">
                <?= field_error($errors, 'name') ?>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-primary">Add doctor</button></div>
        </form>
        <p class="small muted">Doctors are never deleted so old medicine records keep the correct name. Use <strong>Hide</strong> instead.</p>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
