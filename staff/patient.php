<?php
require __DIR__ . '/../includes/bootstrap.php';
$staff = require_role('staff');

$uid = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$acct = q("SELECT * FROM users WHERE id = ? AND role = 'patient'", [$uid])->fetch();
if (!$acct) {
    flash('error', 'Patient account not found.');
    redirect('staff/patients.php');
}
$self = q('SELECT * FROM patients WHERE user_id = ? AND is_self = 1 AND is_deleted = 0 LIMIT 1', [$uid])->fetch() ?: null;

const DEACTIVATE_REASONS = [
    'PhilHealth / YAKAP number does not match the ID',
    'Not a resident of the barangay',
    'Duplicate account',
    'Unclear or wrong ID picture',
    'Requested by the patient',
    'Other',
];

if (is_post()) {
    verify_csrf();
    $action = input('action');
    $back = 'staff/patient.php?id=' . $uid;

    if ($action === 'verify') {
        if (!(int)$acct['is_active'] || $acct['approval_status'] !== 'pending' || !$self) {
            flash('error', 'Only accounts waiting for approval can be verified.');
            redirect($back);
        }
        q("UPDATE users SET approval_status = 'verified', verified_at = NOW(), verified_by = ? WHERE id = ?", [$staff['id'], $uid]);
        notify($uid, 'Your account has been verified. You can now book appointments.', 'patient/book.php');

        $name = $self['first_name'];
        $link = absolute_url('login.php');
        $subject = 'Your ' . APP_NAME . ' account is approved';
        $text = "Hi $name,\n\nGood news! The health center has verified your account. You can now log in and book an appointment:\n$link\n\n"
              . "Please bring a valid ID on your visit.\n\n" . APP_NAME;
        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#1b2333;line-height:1.5">'
              . '<h2 style="color:#2b3090;margin:0 0 12px">Your account is approved</h2>'
              . '<p>Hi ' . h($name) . ',</p>'
              . '<p>Good news! The ' . h(APP_NAME) . ' has verified your PhilHealth details. You can now book consultations and result readings online.</p>'
              . '<p style="margin:24px 0"><a href="' . h($link) . '" style="background:#2b3090;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:bold">Log in and book</a></p>'
              . '<p style="color:#5a6478;font-size:13px">Or open this link: ' . h($link) . '</p>'
              . '<p style="color:#5a6478;font-size:13px">Please bring a valid ID on your visit.</p></div>';
        $sent = send_mail($acct['email'], $subject, $html, $text);

        flash('success', full_name($self) . ' is now verified and can book.'
            . ($sent ? ' An email was sent to ' . $acct['email'] . '.' : (mail_ready() ? ' (The email could not be sent, but they will see a notification when they log in.)' : ' (Email is not set up yet, so they will only see an in-app notification.)')));
        redirect($back);
    }

    if ($action === 'deactivate') {
        $reason = input('reason');
        $other  = mb_substr(strip_tags(input('other')), 0, 200);
        if (!in_array($reason, DEACTIVATE_REASONS, true)) {
            flash('error', 'Please choose a reason.');
            redirect($back);
        }
        if ($reason === 'Other' && mb_strlen($other) < 5) {
            flash('error', 'Please type the reason (at least 5 characters).');
            redirect($back);
        }
        $full = $reason === 'Other' ? $other : $reason . ($other !== '' ? ' - ' . $other : '');
        $pdo = db();
        $pdo->beginTransaction();
        q('UPDATE users SET is_active = 0, deactivated_reason = ?, deactivated_at = NOW() WHERE id = ?', [$full, $uid]);
        // Free up their upcoming time slots
        $n = q("UPDATE appointments SET status = 'cancelled', cancelled_by = 'staff', cancel_reason = ?, cancelled_at = NOW()
                WHERE user_id = ? AND status = 'upcoming'", ['Account deactivated: ' . $full, $uid])->rowCount();
        q('DELETE FROM sessions WHERE data LIKE ?', ['%user_id|i:' . $uid . ';%']);
        $pdo->commit();
        flash('success', 'Account deactivated.' . ($n ? " $n upcoming appointment(s) were cancelled." : ''));
        redirect($back);
    }

    if ($action === 'reactivate') {
        q('UPDATE users SET is_active = 1, deactivated_reason = NULL, deactivated_at = NULL WHERE id = ?', [$uid]);
        flash('success', 'Account reactivated.' . ($acct['approval_status'] === 'pending' ? ' It is still waiting for approval.' : ''));
        redirect($back);
    }

    if ($action === 'return') {
        // Send back to the patient to fix their registration (e.g. blurry ID)
        $note = mb_substr(strip_tags(input('note')), 0, 200);
        if (mb_strlen($note) < 5) {
            flash('error', 'Please tell the patient what to fix (at least 5 characters).');
            redirect($back);
        }
        q("UPDATE users SET approval_status = 'new', review_note = ? WHERE id = ? AND approval_status = 'pending'", [$note, $uid]);
        notify($uid, 'Please update your registration: ' . $note, 'patient/profile.php');
        flash('success', 'Sent back to the patient with your note. It will return to "Waiting for approval" once they resubmit.');
        redirect($back);
    }
    redirect($back);
}

$deps = q('SELECT * FROM patients WHERE user_id = ? AND is_self = 0 AND is_deleted = 0 ORDER BY dep_seq', [$uid])->fetchAll();
$holderNo = $self['philhealth_no'] ?? '';
$appts = q('SELECT a.*, p.first_name, p.middle_name, p.last_name, p.suffix FROM appointments a JOIN patients p ON p.id = a.patient_id
            WHERE a.user_id = ? ORDER BY a.appt_date DESC, a.slot_time DESC LIMIT 100', [$uid])->fetchAll();
$meds = q('SELECT d.quantity, d.created_at, m.name AS medicine, doc.name AS doctor, s.display_name AS staff_name, s.email AS staff_email,
                  p.first_name, p.middle_name, p.last_name, p.suffix
           FROM dispensations d JOIN patients p ON p.id = d.patient_id JOIN medicines m ON m.id = d.medicine_id
           JOIN doctors doc ON doc.id = d.doctor_id JOIN users s ON s.id = d.staff_id
           WHERE p.user_id = ? ORDER BY d.created_at DESC LIMIT 100', [$uid])->fetchAll();
$verifier = $acct['verified_by'] ? q('SELECT display_name, email FROM users WHERE id = ?', [$acct['verified_by']])->fetch() : null;
$isPending = (int)$acct['is_active'] && $acct['approval_status'] === 'pending';

$pageTitle = $self ? full_name($self) : $acct['email'];
$activeNav = 'patients';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head row-between">
    <div>
        <a class="back" href="<?= h(url('staff/patients.php')) ?>">&larr; Patient profiles</a>
        <h1><?= h($pageTitle) ?></h1>
        <p><?= account_badge($acct) ?> <span class="muted small" style="margin-left:6px"><?= h($acct['email']) ?> &middot; joined <?= h(date('M j, Y', strtotime($acct['created_at']))) ?></span></p>
    </div>
    <div class="row-actions">
        <?php if ($isPending): ?>
            <button type="button" class="btn btn-ghost" data-open="returnModal">Ask to fix</button>
            <button type="button" class="btn btn-primary" data-open="verifyModal">Verify</button>
        <?php else: ?>
            <button type="button" class="btn btn-primary" disabled title="Only accounts waiting for approval can be verified">Verify</button>
        <?php endif; ?>
        <?php if ((int)$acct['is_active']): ?>
            <button type="button" class="btn btn-danger-ghost" data-open="deactivateModal">Deactivate</button>
        <?php else: ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $uid ?>"><input type="hidden" name="action" value="reactivate">
                <button type="submit" class="btn btn-ghost">Reactivate</button></form>
        <?php endif; ?>
    </div>
</div>

<?php if (!(int)$acct['is_active']): ?>
    <div class="alert alert-error">Deactivated on <?= h(date('M j, Y g:i A', strtotime($acct['deactivated_at']))) ?>. Reason: <?= h($acct['deactivated_reason']) ?></div>
<?php elseif ($acct['approval_status'] === 'verified' && $acct['verified_at']): ?>
    <div class="alert alert-success">Verified on <?= h(date('M j, Y g:i A', strtotime($acct['verified_at']))) ?><?= $verifier ? ' by ' . h($verifier['display_name'] ?: $verifier['email']) : '' ?>.</div>
<?php elseif ($acct['approval_status'] === 'new'): ?>
    <div class="alert alert-info">This patient hasn't finished their registration yet.</div>
<?php endif; ?>

<div class="grid grid-2 align-start">
    <div class="card">
        <h2>Patient info</h2>
        <?php if (!$self): ?>
            <p class="empty">No details yet.</p>
        <?php else: ?>
        <dl class="summary-list">
            <dt>PhilHealth / YAKAP</dt><dd class="mono"><?= h($self['philhealth_no']) ?></dd>
            <dt>Full name</dt><dd><?= h(full_name($self)) ?></dd>
            <dt>Sex</dt><dd><?= h($self['sex']) ?></dd>
            <dt>Birth date</dt><dd><?= h(date('M j, Y', strtotime($self['birth_date']))) ?> (<?= age_of($self['birth_date']) ?> yrs)</dd>
            <dt>Mobile</dt><dd><?= h($self['mobile']) ?></dd>
            <dt>Email</dt><dd><?= h($acct['email']) ?></dd>
            <dt>Address</dt><dd><?= h(full_address($self)) ?></dd>
        </dl>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2>PhilHealth ID</h2>
        <?php if ($self && $self['philhealth_file']): $isPdf = substr($self['philhealth_file'], -4) === '.pdf'; ?>
            <?php if ($isPdf): ?>
                <p><a class="btn btn-ghost" target="_blank" href="<?= h(url('file.php?f=' . $self['philhealth_file'])) ?>">Open PDF</a></p>
            <?php else: ?>
                <a target="_blank" href="<?= h(url('file.php?f=' . $self['philhealth_file'])) ?>" title="Open full size">
                    <img class="id-preview" src="<?= h(url('file.php?f=' . $self['philhealth_file'])) ?>" alt="Uploaded PhilHealth ID">
                </a>
            <?php endif; ?>
            <p class="small muted">Check that the number and name match, and that the address is in the barangay.</p>
        <?php else: ?>
            <p class="empty">No ID uploaded.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2>Dependents</h2>
    <?php if (!$deps): ?><p class="empty">No dependents.</p><?php else: ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>PhilHealth / ID no.</th><th>Name</th><th>Relationship</th><th>Sex</th><th>Age</th><th>Mobile</th></tr></thead>
        <tbody>
        <?php foreach ($deps as $d): ?>
            <tr><td class="mono small"><?= h(patient_pid($d, $holderNo)) ?: '-' ?></td><td><?= h(full_name($d)) ?></td><td><?= h($d['relationship']) ?></td>
                <td><?= h($d['sex']) ?></td><td><?= age_of($d['birth_date']) ?></td><td><?= h($d['mobile']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Appointment history</h2>
    <?php if (!$appts): ?><p class="empty">No appointments yet.</p><?php else: ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Ref.</th><th>Patient</th><th>Service</th><th>Date</th><th>Time slot</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($appts as $a): ?>
            <tr><td class="mono"><?= h($a['queue_no']) ?></td><td><?= h(full_name($a)) ?></td><td><?= h($a['service']) ?></td>
                <td class="nowrap"><a href="<?= h(url('staff/index.php?month=' . substr($a['appt_date'], 0, 7) . '&date=' . $a['appt_date'])) ?>"><?= h(nice_date($a['appt_date'])) ?></a></td>
                <td class="nowrap"><?= h(slot_label($a['slot_time'])) ?></td><td><?= status_badge($a['status'], $a['cancelled_by']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Medicines given</h2>
    <?php if (!$meds): ?><p class="empty">No medicine given yet.</p><?php else: ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Date and time</th><th>Patient</th><th>Medicine</th><th class="right">Qty</th><th>Doctor</th><th>Distributed by</th></tr></thead>
        <tbody>
        <?php foreach ($meds as $g): ?>
            <tr><td class="nowrap"><?= h(date('M j, Y g:i A', strtotime($g['created_at']))) ?></td><td><?= h(full_name($g)) ?></td>
                <td><?= h($g['medicine']) ?></td><td class="right mono"><?= number_format((int)$g['quantity']) ?></td>
                <td><?= h($g['doctor']) ?></td><td><?= h($g['staff_name'] ?: $g['staff_email']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<div class="modal" id="verifyModal" hidden>
    <div class="modal-box">
        <h3>Verify this account?</h3>
        <p class="muted">Confirm that the PhilHealth / YAKAP number <strong><?= h($self['philhealth_no'] ?? '') ?></strong> matches the uploaded ID
            and that <?= h($self ? full_name($self) : 'the patient') ?> is a resident of the barangay.</p>
        <p class="small muted">They will be able to book right away<?= mail_ready() ? ' and will get an email' : '' ?>.</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $uid ?>">
            <input type="hidden" name="action" value="verify">
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" data-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Yes, verify</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="returnModal" hidden>
    <div class="modal-box">
        <h3>Ask the patient to fix their registration</h3>
        <form method="post" data-validate-form novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $uid ?>">
            <input type="hidden" name="action" value="return">
            <div class="field">
                <label for="note">What should they fix? <span class="req">*</span></label>
                <textarea id="note" name="note" rows="3" maxlength="200" data-validate="required|min:5|max:200"
                          placeholder="e.g. The ID picture is blurry. Please upload a clearer photo."></textarea>
                <small class="field-error"></small>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" data-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Send to patient</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="deactivateModal" hidden>
    <div class="modal-box">
        <h3>Deactivate this account?</h3>
        <p class="muted">The patient won't be able to log in or book. Their upcoming appointments will be cancelled. Their records are kept, and you can reactivate the account later.</p>
        <form method="post" data-validate-form novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $uid ?>">
            <input type="hidden" name="action" value="deactivate">
            <div class="field">
                <label for="reason">Reason <span class="req">*</span></label>
                <select id="reason" name="reason" data-validate="required"><?= select_options(DEACTIVATE_REASONS, '') ?></select>
                <small class="field-error"></small>
            </div>
            <div class="field">
                <label for="other">Details <span class="opt">(required if "Other")</span></label>
                <textarea id="other" name="other" rows="2" maxlength="200" data-validate="max:200"></textarea>
                <small class="field-error"></small>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" data-close>Cancel</button>
                <button type="submit" class="btn btn-danger">Deactivate</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
