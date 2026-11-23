<?php
require __DIR__ . '/../includes/bootstrap.php';
$staff = require_role('staff');

if (is_post()) {
    verify_csrf();
    $action = input('action');
    $id = (int)input('id');
    $back = input('back') !== '' && preg_match('/^staff\/index\.php\?month=\d{4}-\d{2}&date=\d{4}-\d{2}-\d{2}$/', input('back'))
        ? input('back') : 'staff/index.php';

    $appt = q('SELECT a.*, p.first_name, p.middle_name, p.last_name, p.suffix, p.mobile
               FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.id = ?', [$id])->fetch();

    if ($action === 'dispense') {
        $reopen = $back . (strpos($back, '?') === false ? '?' : '&') . 'open=appt-' . $id;
        if (!$appt || $appt['status'] !== 'completed') {
            flash('error', 'Medicine can only be recorded after the patient is marked as arrived.');
            redirect($back);
        }
        $rows = $_POST['meds'] ?? [];
        if (!is_array($rows) || !$rows) {
            flash('error', 'Add at least one medicine.');
            redirect($reopen);
        }
        $doctorIds = array_column(active_doctors(), 'name', 'id');
        $items = [];
        $errs = [];
        foreach (array_values($rows) as $i => $r) {
            $n = $i + 1;
            $name = is_array($r) && is_string($r['name'] ?? null) ? trim($r['name']) : '';
            $qtyS = is_array($r) && is_string($r['qty'] ?? null) ? trim($r['qty']) : '';
            $doc  = is_array($r) ? (int)($r['doctor_id'] ?? 0) : 0;
            $med  = $name !== '' ? q('SELECT id, name, in_stock FROM medicines WHERE name = ?', [$name])->fetch() : null;
            if (!$med) { $errs[] = "Row $n: choose a medicine from the list."; continue; }
            if (!preg_match('/^\d{1,6}$/', $qtyS) || (int)$qtyS < 1 || (int)$qtyS > 100000) { $errs[] = "Row $n: quantity must be a whole number from 1."; continue; }
            if (!isset($doctorIds[$doc])) { $errs[] = "Row $n: choose the prescribing doctor."; continue; }
            $items[] = ['med' => $med, 'qty' => (int)$qtyS, 'doctor_id' => $doc];
        }
        if ($errs) {
            flash('error', implode(' ', $errs));
            redirect($reopen);
        }
        $pdo = db();
        try {
            $pdo->beginTransaction();
            foreach ($items as $it) {
                // Only subtract if there is enough stock (prevents negative stock even with two staff at once)
                $ok = q('UPDATE medicines SET in_stock = in_stock - ? WHERE id = ? AND in_stock >= ?',
                    [$it['qty'], $it['med']['id'], $it['qty']])->rowCount();
                if ($ok !== 1) {
                    $left = (int)q('SELECT in_stock FROM medicines WHERE id = ?', [$it['med']['id']])->fetchColumn();
                    throw new RuntimeException('Not enough stock for ' . $it['med']['name'] . ' (only ' . number_format($left) . ' left). Nothing was saved.');
                }
                q('INSERT INTO dispensations (appointment_id, patient_id, medicine_id, quantity, doctor_id, staff_id) VALUES (?,?,?,?,?,?)',
                    [$appt['id'], $appt['patient_id'], $it['med']['id'], $it['qty'], $it['doctor_id'], $staff['id']]);
            }
            $pdo->commit();
            flash('success', count($items) . ' medicine record(s) saved for ' . full_name($appt) . '. Stock was updated.');
        } catch (RuntimeException $e) {
            $pdo->rollBack();
            flash('error', $e->getMessage());
        }
        redirect($reopen);
    }

    if (!$appt || $appt['status'] !== 'upcoming') {
        flash('error', 'This appointment is no longer upcoming.');
        redirect($back);
    }

    if ($action === 'arrived') {
        if ($appt['appt_date'] !== date('Y-m-d')) {
            flash('error', 'You can only mark patients as arrived on the day of their appointment.');
        } else {
            q("UPDATE appointments SET status = 'completed' WHERE id = ? AND status = 'upcoming'", [$id]);
            flash('success', full_name($appt) . ' marked as arrived. Status set to Completed. You can now record the medicine given.');
            redirect($back . (strpos($back, '?') === false ? '?' : '&') . 'open=appt-' . $id);
        }
        redirect($back);
    }

    if ($action === 'cancel') {
        $reason    = input('reason');
        $offerDate = input('offer_date');
        $offerTime = input('offer_time');
        $errs = [];
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 255) $errs[] = 'Reason must be 5 to 255 characters.';
        if ($e = booking_date_error($offerDate)) $errs[] = 'Offered date: ' . $e;
        if (!find_booking_slot($offerTime)) $errs[] = 'Please choose a valid time slot to offer.';

        if (!$errs) {
            try {
                with_booking_lock(function () use ($appt, $reason, $offerDate, $offerTime) {
                    if (!slot_is_available($offerDate, $offerTime)) {
                        throw new RuntimeException('The offered slot is not available. Please choose another slot.');
                    }
                    q("UPDATE appointments SET status = 'cancelled', cancelled_by = 'staff', cancel_reason = ?, cancelled_at = NOW(),
                       offer_date = ?, offer_time = ?, offer_status = 'pending' WHERE id = ? AND status = 'upcoming'",
                       [strip_tags($reason), $offerDate, $offerTime . ':00', $appt['id']]);
                });

                $msg = 'Your appointment (Queue ' . $appt['queue_no'] . ') for ' . full_name($appt) . ' on '
                     . nice_date($appt['appt_date']) . ', ' . slot_label($appt['slot_time']) . ' was cancelled by the health center. Reason: '
                     . strip_tags($reason) . '. New schedule offered: ' . nice_date($offerDate) . ', ' . slot_label($offerTime)
                     . '. Please accept or decline in My Bookings.';
                notify((int)$appt['user_id'], $msg, 'patient/bookings.php');
                $sms = send_sms($appt['mobile'], APP_NAME . ': ' . $msg);

                flash('success', 'Appointment cancelled. The patient was notified' . ($sms ? ' (in-app + SMS).' : ' in the app.'));
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
            }
        } else {
            flash('error', implode(' ', $errs));
        }
        redirect($back);
    }
    redirect($back);
}

$month = input('month', $_GET);
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) $month = date('Y-m');
$selDate = input('date', $_GET);
if (!valid_date_string($selDate)) $selDate = (substr(date('Y-m-d'), 0, 7) === $month) ? date('Y-m-d') : $month . '-01';

$first = new DateTime($month . '-01');
$daysInMonth = (int)$first->format('t');
$startWeekday = (int)$first->format('w');
$prevMonth = (clone $first)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $first)->modify('+1 month')->format('Y-m');

$counts = [];
foreach (q("SELECT appt_date, SUM(status = 'upcoming') AS up, SUM(status = 'completed') AS done, COUNT(*) AS total
            FROM appointments WHERE appt_date BETWEEN ? AND ? GROUP BY appt_date",
          [$month . '-01', $month . '-' . $daysInMonth])->fetchAll() as $r) {
    $counts[$r['appt_date']] = $r;
}

$dayAppts = q('SELECT a.*, p.first_name, p.middle_name, p.last_name, p.suffix, p.sex, p.birth_date, p.mobile,
                      p.region, p.province, p.municipality, p.barangay, p.district, p.zone, p.street,
                      p.relationship, p.is_self, p.philhealth_no, p.dep_seq, p.user_id AS holder_id, u.email AS account_email
               FROM appointments a JOIN patients p ON p.id = a.patient_id JOIN users u ON u.id = a.user_id
               WHERE a.appt_date = ? ORDER BY a.slot_time, a.id', [$selDate])->fetchAll();
$bySlot = [];
foreach ($dayAppts as $a) $bySlot[substr($a['slot_time'], 0, 5)][] = $a;

// Medicine already given for this day's appointments
$given = [];
if ($dayAppts) {
    $ids = array_column($dayAppts, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    foreach (q("SELECT d.*, m.name AS medicine, doc.name AS doctor, u.display_name AS staff_name, u.email AS staff_email
                FROM dispensations d JOIN medicines m ON m.id = d.medicine_id JOIN doctors doc ON doc.id = d.doctor_id
                JOIN users u ON u.id = d.staff_id WHERE d.appointment_id IN ($in) ORDER BY d.id", $ids)->fetchAll() as $g) {
        $given[$g['appointment_id']][] = $g;
    }
}
$meds = q('SELECT name, in_stock FROM medicines ORDER BY name')->fetchAll();
$doctors = active_doctors();
$openModal = preg_match('/^appt-\d+$/', input('open', $_GET)) ? input('open', $_GET) : '';
$staffName = $staff['display_name'] !== '' ? $staff['display_name'] : $staff['email'];

$backUrl = 'staff/index.php?month=' . $month . '&date=' . $selDate;
$isToday = $selDate === date('Y-m-d');

$pageTitle = 'Appointments';
$activeNav = 'calendar';
$pageScripts = ['calendar.js', 'dispense.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Appointments</h1><p>Pick a day to see who is booked. Open an appointment to verify the patient.</p></div>

<div class="staff-layout">
    <div class="card">
        <div class="cal-head">
            <a class="btn btn-sm btn-ghost" href="<?= h(url('staff/index.php?month=' . $prevMonth)) ?>">&larr;</a>
            <h2><?= h($first->format('F Y')) ?></h2>
            <a class="btn btn-sm btn-ghost" href="<?= h(url('staff/index.php?month=' . $nextMonth)) ?>">&rarr;</a>
        </div>
        <div class="month-grid">
            <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $wd): ?><div class="wd"><?= $wd ?></div><?php endforeach; ?>
            <?php for ($i = 0; $i < $startWeekday; $i++): ?><div class="day empty"></div><?php endfor; ?>
            <?php for ($d = 1; $d <= $daysInMonth; $d++):
                $date = sprintf('%s-%02d', $month, $d);
                $isSun = date('w', strtotime($date)) === '0';
                $c = $counts[$date] ?? null;
                $cls = 'day' . ($isSun ? ' sunday' : '') . ($date === $selDate ? ' selected' : '') . ($date === date('Y-m-d') ? ' today' : ''); ?>
                <a class="<?= $cls ?>" href="<?= h(url('staff/index.php?month=' . $month . '&date=' . $date)) ?>">
                    <span class="num"><?= $d ?></span>
                    <?php if ($isSun): ?><span class="tag muted">Closed</span>
                    <?php elseif ($c && $c['up'] > 0): ?><span class="tag tag-up"><?= (int)$c['up'] ?></span>
                    <?php elseif ($c && $c['total'] > 0): ?><span class="tag tag-past"><?= (int)$c['total'] ?> <?= $date < date('Y-m-d') ? 'past' : 'cancelled' ?></span><?php endif; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>

    <div class="card">
        <h2><?= h(nice_date($selDate)) ?></h2>
        <?php if (date('w', strtotime($selDate)) === '0'): ?>
            <p class="empty">Closed on Sundays.</p>
        <?php else: ?>
        <p class="muted small">Booking slots only. Walk-in slots are not listed.</p>
        <ul class="slot-list">
            <?php foreach (all_slots() as $s): if ($s['type'] !== 'booking') continue; $list = $bySlot[$s['start']] ?? []; ?>
            <li>
                <span class="slot-time"><?= h(slot_label($s['start'])) ?></span>
                <span class="slot-body">
                <?php if (!$list): ?>
                    <span class="slot-free">Open</span>
                <?php else: foreach ($list as $a): ?>
                    <button type="button" class="slot-appt st-<?= h($a['status']) ?>" data-open="appt-<?= (int)$a['id'] ?>">
                        <strong><?= h(short_queue($a['queue_no'])) ?></strong> <?= h(full_name($a)) ?>
                        <?php if ($a['priority'] !== 'Regular'): ?><?= priority_chip($a['priority']) ?><?php endif; ?>
                        <?= status_badge($a['status'], $a['cancelled_by']) ?>
                    </button>
                <?php endforeach; endif; ?>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<?php foreach ($dayAppts as $a): ?>
<div class="modal" id="appt-<?= (int)$a['id'] ?>" <?= $openModal === 'appt-' . (int)$a['id'] ? 'data-autoopen' : 'hidden' ?>>
    <div class="modal-box wide">
        <div class="row-between">
            <h3><?= h(full_name($a)) ?> <span class="muted" style="font-weight:500">#<?= h(short_queue($a['queue_no'])) ?></span></h3>
            <?= status_badge($a['status'], $a['cancelled_by']) ?>
        </div>
        <div class="grid grid-2">
            <div>
                <h4>Patient info</h4>
                <dl class="summary-list compact">
                    <dt>Name</dt><dd><?= h(full_name($a)) ?></dd>
                    <dt>Sex / Age</dt><dd><?= h($a['sex']) ?>, <?= age_of($a['birth_date']) ?> yrs (<?= h(date('M j, Y', strtotime($a['birth_date']))) ?>)</dd>
                    <dt>Mobile</dt><dd><?= h($a['mobile']) ?></dd>
                    <dt>Address</dt><dd><?= h(full_address($a)) ?></dd>
                    <dt>PhilHealth</dt><dd class="mono"><?= h(patient_pid($a)) ?: '<span class="muted">None</span>' ?></dd>
                    <dt>Booked by</dt><dd><?= h($a['account_email']) ?><?= $a['is_self'] ? ' (self)' : ' (' . h($a['relationship']) . ')' ?></dd>
                </dl>
            </div>
            <div>
                <h4>Booking details</h4>
                <dl class="summary-list compact">
                    <dt>Reference</dt><dd class="mono"><?= h($a['queue_no']) ?></dd>
                    <dt>Schedule</dt><dd><?= h(nice_date($a['appt_date'])) ?>, <?= h(slot_label($a['slot_time'])) ?></dd>
                    <dt>Visit type</dt><dd><?= h($a['visit_type']) ?></dd>
                    <dt>Service</dt><dd><?= h($a['service']) ?></dd>
                    <dt>Priority</dt><dd><?= priority_chip($a['priority']) ?></dd>
                    <dt>Notes</dt><dd><?= $a['notes'] ? h($a['notes']) : '<span class="muted">None</span>' ?></dd>
                    <dt>Files</dt><dd>
                        <?php if ($a['result_file']): ?><a target="_blank" href="<?= h(url('file.php?f=' . $a['result_file'])) ?>">Results / document</a><br><?php endif; ?>
                        <?php if ($a['id_file']): ?><a target="_blank" href="<?= h(url('file.php?f=' . $a['id_file'])) ?>">Senior / PWD ID</a><?php endif; ?>
                        <?php if (!$a['result_file'] && !$a['id_file']): ?><span class="muted">None</span><?php endif; ?>
                    </dd>
                    <?php if ($a['status'] === 'cancelled'): ?>
                        <dt>Cancel reason</dt><dd><?= h($a['cancel_reason']) ?></dd>
                        <?php if ($a['offer_date']): ?><dt>Offered</dt><dd><?= h(nice_date($a['offer_date'])) ?>, <?= h(slot_label($a['offer_time'])) ?> (<?= h($a['offer_status']) ?>)</dd><?php endif; ?>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <?php if ($a['status'] === 'completed'): $list = $given[$a['id']] ?? []; ?>
        <div class="med-given">
            <h4>Medicine given</h4>
            <?php if ($list): ?>
            <div class="table-wrap">
            <table class="table compact-table">
                <thead><tr><th>Medicine</th><th class="right">Qty</th><th>Doctor</th><th>Distributed by</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach ($list as $g): ?>
                    <tr><td><?= h($g['medicine']) ?></td><td class="right mono"><?= number_format((int)$g['quantity']) ?></td>
                        <td><?= h($g['doctor']) ?></td><td><?= h($g['staff_name'] ?: $g['staff_email']) ?></td>
                        <td class="nowrap"><?= h(date('g:i A', strtotime($g['created_at']))) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php else: ?>
                <p class="muted small">No medicine recorded yet.</p>
            <?php endif; ?>

            <form method="post" class="dispense-form" novalidate data-patient="<?= h(full_name($a)) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="dispense">
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <input type="hidden" name="back" value="<?= h($backUrl) ?>">
                <div class="disp-rows"></div>
                <div class="disp-foot">
                    <button type="button" class="btn btn-sm btn-ghost" data-add-row>+ Add medicine</button>
                    <span class="small muted">Distributed by: <strong><?= h($staffName) ?></strong></span>
                    <button type="submit" class="btn btn-sm btn-primary" hidden data-disp-submit>Submit</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" data-close>Close</button>
            <?php if ($a['status'] === 'upcoming'): ?>
                <button type="button" class="btn btn-danger-ghost" data-close data-open="cancelModal"
                        data-fill='<?= h(json_encode(['id' => $a['id'], 'name' => full_name($a)])) ?>'>Cancel appointment</button>
                <form method="post" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="arrived">
                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <input type="hidden" name="back" value="<?= h($backUrl) ?>">
                    <button type="submit" class="btn btn-primary" <?= $isToday ? '' : 'disabled title="Available on the appointment day"' ?>>Mark as arrived</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<datalist id="medList">
    <?php foreach ($meds as $m): ?><option value="<?= h($m['name']) ?>" label="In stock: <?= number_format((int)$m['in_stock']) ?>"></option><?php endforeach; ?>
</datalist>
<script type="application/json" id="medStock"><?= json_encode(array_column($meds, 'in_stock', 'name'), JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

<template id="dispRowTpl">
    <div class="disp-row">
        <div class="field">
            <label>Medicine <span class="req">*</span></label>
            <input type="text" list="medList" data-name="name" maxlength="120" placeholder="Type to search..." autocomplete="off">
            <small class="field-error"></small>
        </div>
        <div class="field qty">
            <label>Quantity <span class="req">*</span></label>
            <input type="text" data-name="qty" inputmode="numeric" maxlength="6" placeholder="0">
            <small class="field-error"></small>
        </div>
        <div class="field">
            <label>Doctor <span class="req">*</span></label>
            <select data-name="doctor_id">
                <option value="">-- Select --</option>
                <?php foreach ($doctors as $d): ?><option value="<?= (int)$d['id'] ?>"><?= h($d['name']) ?></option><?php endforeach; ?>
            </select>
            <small class="field-error"></small>
        </div>
        <button type="button" class="btn btn-sm btn-danger-ghost disp-remove" aria-label="Remove row">&times;</button>
    </div>
</template>

<div class="modal" id="dispenseConfirm" hidden>
    <div class="modal-box wide">
        <h3>Double-check before giving</h3>
        <div class="alert alert-warning" style="display:block">Make sure the <strong>medicine, strength/dosage and quantity</strong> match the doctor's prescription
            for <strong data-slot="patient"></strong>. Stock is deducted right away and this can't be edited later.</div>
        <div class="table-wrap"><table class="table compact-table"><thead><tr><th>Medicine</th><th class="right">Qty</th><th>Doctor</th></tr></thead><tbody id="dispSummary"></tbody></table></div>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" data-close>Go back and check</button>
            <button type="button" class="btn btn-primary" id="dispYes">Yes, it's correct. Save</button>
        </div>
    </div>
</div>

<div class="modal" id="cancelModal" hidden>
    <div class="modal-box wide">
        <h3>Cancel <span data-slot="name"></span>'s appointment</h3>
        <p class="muted small">The patient is notified and can accept the new schedule you offer or choose another one.</p>
        <form method="post" data-validate-form novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="id" data-slot="id">
            <input type="hidden" name="back" value="<?= h($backUrl) ?>">
            <div class="field">
                <label for="reason">Reason <span class="req">*</span></label>
                <textarea id="reason" name="reason" rows="2" maxlength="255" data-validate="required|min:5|max:255"
                          placeholder="e.g. Doctor is attending an emergency"></textarea>
                <small class="field-error"></small>
            </div>
            <div class="grid grid-2">
                <div class="field">
                    <label>Offer new date <span class="req">*</span></label>
                    <div class="datepicker" data-input="offer_date" data-min="<?= first_booking_date() ?>" data-max="<?= last_booking_date() ?>"></div>
                    <input type="hidden" id="offer_date" name="offer_date" data-validate="required|bookdate">
                    <small class="field-error"></small>
                </div>
                <div class="field">
                    <label for="offer_time">Offer time slot <span class="req">*</span></label>
                    <select id="offer_time" name="offer_time" data-slots-for="offer_date" data-validate="required">
                        <option value="">-- Pick a date first --</option>
                    </select>
                    <small class="field-error"></small>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" data-close>Keep appointment</button>
                <button type="submit" class="btn btn-danger">Cancel and offer new schedule</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
