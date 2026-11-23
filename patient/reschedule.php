<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('patient');
$uid = (int)$user['id'];
require_verified_patient($user);

$from = (int)($_GET['from'] ?? 0);
$appt = q('SELECT a.*, p.first_name, p.middle_name, p.last_name, p.suffix
           FROM appointments a JOIN patients p ON p.id = a.patient_id
           WHERE a.id = ? AND a.user_id = ?', [$from, $uid])->fetch();

if (!$appt || !can_reschedule($appt)) {
    flash('error', 'This appointment cannot be rescheduled.');
    redirect('patient/bookings.php');
}

$errors = [];
$date = '';
$slot = '';

if (is_post()) {
    verify_csrf();
    $date = input('appt_date');
    $slot = input('slot_time');

    if ($e = booking_date_error($date)) $errors['appt_date'] = $e;
    if ($slot === '') $errors['slot_time'] = 'Please choose a time slot.';
    elseif (!find_booking_slot($slot)) $errors['slot_time'] = 'Invalid time slot.';

    if (!$errors) {
        try {
            $newId = create_appointment([
                'user_id' => $uid, 'patient_id' => $appt['patient_id'], 'booked_for' => $appt['booked_for'],
                'visit_type' => $appt['visit_type'], 'service' => $appt['service'],
                'appt_date' => $date, 'slot_time' => $slot,
                'priority' => $appt['priority'], 'notes' => $appt['notes'],
                'result_file' => $appt['result_file'], 'id_file' => $appt['id_file'],
                'rescheduled_from' => $appt['id'],
            ]);
            redirect('patient/book.php?done=' . $newId);
        } catch (RuntimeException $e) {
            $errors['slot_time'] = $e->getMessage();
        }
    }
}

$pageTitle = 'Choose a new schedule';
$activeNav = 'bookings';
$pageScripts = ['calendar.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <a class="back" href="<?= h(url('patient/bookings.php')) ?>">&larr; My bookings</a>
    <h1>Choose a new schedule</h1>
</div>

<div class="card">
    <dl class="summary-list compact">
        <dt>Patient</dt><dd><?= h(full_name($appt)) ?></dd>
        <dt>Service</dt><dd><?= h($appt['service']) ?> (<?= h($appt['visit_type']) ?>)</dd>
        <dt>Old schedule</dt><dd><?= h(nice_date($appt['appt_date'])) ?>, <?= h(slot_label($appt['slot_time'])) ?> &middot; <?= status_badge($appt['status'], $appt['cancelled_by']) ?></dd>
    </dl>
</div>

<form method="post" class="card" id="reschedForm" data-validate-form novalidate>
    <?= csrf_field() ?>
    <div class="grid grid-2">
        <div class="field">
            <label>New date <span class="req">*</span> <span class="opt">(Sundays closed)</span></label>
            <div class="datepicker" data-input="appt_date" data-min="<?= first_booking_date() ?>" data-max="<?= last_booking_date() ?>"></div>
            <input type="hidden" id="appt_date" name="appt_date" value="<?= h($date) ?>" data-validate="required|bookdate">
            <?= field_error($errors, 'appt_date') ?>
        </div>
        <div class="field">
            <label for="slot_time">New time slot <span class="req">*</span></label>
            <select id="slot_time" name="slot_time" data-slots-for="appt_date" data-selected="<?= h($slot) ?>"
                    data-validate="required" class="<?= invalid_class($errors, 'slot_time') ?>">
                <option value="">-- Pick a date first --</option>
            </select>
            <?= field_error($errors, 'slot_time') ?>
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= h(url('patient/bookings.php')) ?>">Cancel</a>
        <button type="submit" class="btn btn-primary" data-confirm="confirmModal">Confirm schedule</button>
    </div>
</form>

<div class="modal" id="confirmModal" hidden>
    <div class="modal-box">
        <h3>Confirm new schedule?</h3>
        <p>A new queue number will be generated. This cannot be changed after confirming.</p>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" data-close>Go back</button>
            <button type="button" class="btn btn-primary" data-confirm-yes>Confirm schedule</button>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
