<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('patient');
$uid = (int)$user['id'];

function own_appointment(int $id, int $uid): ?array
{
    return q('SELECT * FROM appointments WHERE id = ? AND user_id = ?', [$id, $uid])->fetch() ?: null;
}

if (is_post()) {
    verify_csrf();
    $action = input('action');
    $appt = own_appointment((int)input('id'), $uid);

    if (!$appt) {
        flash('error', 'Appointment not found.');
        redirect('patient/bookings.php');
    }

    if ($action === 'cancel') {
        $reason = input('reason');
        if ($appt['status'] !== 'upcoming') {
            flash('error', 'Only upcoming appointments can be cancelled.');
        } elseif (!in_array($reason, CANCEL_REASONS, true)) {
            flash('error', 'Please choose a reason for cancelling.');
        } else {
            q("UPDATE appointments SET status = 'cancelled', cancelled_by = 'patient', cancel_reason = ?, cancelled_at = NOW()
               WHERE id = ? AND status = 'upcoming'", [$reason, $appt['id']]);
            flash('success', 'Your appointment has been cancelled.');
            redirect('patient/bookings.php?cancelled=' . $appt['id']);
        }
        redirect('patient/bookings.php');
    }

    $isPendingOffer = $appt['status'] === 'cancelled' && $appt['cancelled_by'] === 'staff' && $appt['offer_status'] === 'pending';

    if ($action === 'accept_offer') {
        if (!$isPendingOffer) {
            flash('error', 'This offer is no longer available.');
            redirect('patient/bookings.php');
        }
        $pdo = db();
        try {
            $pdo->beginTransaction();

            q("UPDATE appointments SET offer_status = 'accepted' WHERE id = ?", [$appt['id']]);
            $newId = create_appointment([
                'user_id' => $uid, 'patient_id' => $appt['patient_id'], 'booked_for' => $appt['booked_for'],
                'visit_type' => $appt['visit_type'], 'service' => $appt['service'],
                'appt_date' => $appt['offer_date'], 'slot_time' => substr($appt['offer_time'], 0, 5),
                'priority' => $appt['priority'], 'notes' => $appt['notes'],
                'result_file' => $appt['result_file'], 'id_file' => $appt['id_file'],
                'rescheduled_from' => $appt['id'],
            ]);
            $pdo->commit();
            redirect('patient/book.php?done=' . $newId);
        } catch (RuntimeException $e) {
            $pdo->rollBack();
            q("UPDATE appointments SET offer_status = 'declined' WHERE id = ?", [$appt['id']]);
            flash('error', 'Sorry, the offered schedule is no longer available. Please pick a new schedule.');
            redirect('patient/reschedule.php?from=' . $appt['id']);
        }
    }

    if ($action === 'decline_offer') {
        if (!$isPendingOffer) {
            flash('error', 'This offer is no longer available.');
            redirect('patient/bookings.php');
        }
        q("UPDATE appointments SET offer_status = 'declined' WHERE id = ?", [$appt['id']]);
        flash('info', 'Offer declined. Please choose a new schedule that works for you.');
        redirect('patient/reschedule.php?from=' . $appt['id']);
    }

    redirect('patient/bookings.php');
}

$select = 'SELECT a.*, p.first_name, p.middle_name, p.last_name, p.suffix, p.relationship, p.is_self
           FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.user_id = ? ';

$offers   = q($select . "AND a.status = 'cancelled' AND a.cancelled_by = 'staff' AND a.offer_status = 'pending' ORDER BY a.offer_date, a.offer_time", [$uid])->fetchAll();
$upcoming = q($select . "AND a.status = 'upcoming' ORDER BY a.appt_date, a.slot_time", [$uid])->fetchAll();
$history  = q($select . 'ORDER BY a.appt_date DESC, a.slot_time DESC', [$uid])->fetchAll();

$HISTORY_FILTERS = ['' => 'All', 'upcoming' => 'Upcoming', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'missed' => 'Missed'];
$show = input('show', $_GET);
if (!isset($HISTORY_FILTERS[$show])) $show = '';
$counts = array_count_values(array_column($history, 'status'));
$shownHistory = $show === '' ? $history : array_values(array_filter($history, fn($r) => $r['status'] === $show));

$selfRec = self_record($uid);
$firstName = $selfRec['first_name'] ?? ($user['display_name'] !== '' ? explode(' ', $user['display_name'])[0] : '');
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$family = dependents_of($uid);

$askResched = null;
if (isset($_GET['cancelled'])) {
    $c = own_appointment((int)$_GET['cancelled'], $uid);
    if ($c && can_reschedule($c)) $askResched = $c;
}

function for_whom(array $a): string
{
    return $a['is_self'] ? 'Myself' : full_name($a) . ' (' . $a['relationship'] . ')';
}

$pageTitle = 'My bookings';
$activeNav = 'bookings';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head row-between">
    <div>
        <h1><?= h($greeting) ?><?= $firstName !== '' ? ', ' . h($firstName) : '' ?></h1>
        <p>You have <?= count($upcoming) ?> upcoming <?= count($upcoming) === 1 ? 'appointment' : 'appointments' ?><?= $offers ? ' and ' . count($offers) . ' ' . (count($offers) === 1 ? 'message' : 'messages') . ' from the health center' : '' ?>.</p>
    </div>
    <a class="btn btn-primary" href="<?= h(url('patient/book.php')) ?>"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Book appointment</a>
</div>

<?php foreach ($offers as $o): ?>
<div class="card notice-card">
    <div class="notice-bar">
        <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
        <strong>The health center moved your appointment</strong>
        <span>Ref. <?= h($o['queue_no']) ?></span>
    </div>
    <p>The appointment for <strong><?= h(for_whom($o)) ?></strong> on
        <?= h(nice_date($o['appt_date'])) ?>, <?= h(slot_label($o['slot_time'])) ?> was cancelled.</p>
    <p class="reason">Reason: <?= h($o['cancel_reason']) ?></p>
    <div class="offer">
        <div>
            <small>They offered this new schedule</small>
            <strong><?= h(nice_date($o['offer_date'])) ?>, <?= h(slot_label($o['offer_time'])) ?></strong>
        </div>
    </div>
    <div class="form-actions">
        <form method="post" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="decline_offer">
            <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <button class="btn btn-ghost" type="submit">Choose another time</button>
        </form>
        <form method="post" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="accept_offer">
            <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <button class="btn btn-primary" type="submit">Accept new schedule</button>
        </form>
    </div>
</div>
<?php endforeach; ?>

<div class="bookings-layout">
<div class="bookings-main">
<h2 class="section-title">Upcoming</h2>
<?php if (!$upcoming): ?>
    <div class="card"><p class="empty">You have no upcoming appointments. <a href="<?= h(url('patient/book.php')) ?>">Book one now</a>.</p></div>
<?php else: ?>
<div class="appt-grid">
    <?php foreach ($upcoming as $a): ?>
    <div class="ticket small-ticket appt-card">
        <div class="ticket-stub">
            <span>Queue no.</span>
            <strong><?= h(short_queue($a['queue_no'])) ?></strong>
            <small><?= h($a['queue_no']) ?></small>
        </div>
        <div class="ticket-body">
            <div class="ticket-head">
                <div>
                    <div class="ticket-when"><?= h(nice_date($a['appt_date'])) ?></div>
                    <div class="ticket-time"><?= h(slot_label($a['slot_time'])) ?></div>
                </div>
                <?= status_badge($a['status']) ?>
            </div>
            <dl class="summary-list compact">
                <dt>For</dt><dd><?= h(for_whom($a)) ?></dd>
                <dt>Service</dt><dd><?= h($a['service']) ?>, <?= h(strtolower($a['visit_type'])) ?></dd>
                <dt>Priority</dt><dd><?= priority_chip($a['priority']) ?></dd>
                <?php if ($a['notes']): ?><dt>Notes</dt><dd><?= h($a['notes']) ?></dd><?php endif; ?>
                <?php if ($a['result_file'] || $a['id_file']): ?><dt>Files</dt><dd>
                    <?php if ($a['result_file']): ?><a target="_blank" href="<?= h(url('file.php?f=' . $a['result_file'])) ?>">Document</a><?php endif; ?>
                    <?php if ($a['result_file'] && $a['id_file']): ?>, <?php endif; ?>
                    <?php if ($a['id_file']): ?><a target="_blank" href="<?= h(url('file.php?f=' . $a['id_file'])) ?>">ID</a><?php endif; ?>
                </dd><?php endif; ?>
            </dl>
            <div class="form-actions">
                <span></span>
                <button type="button" class="btn btn-sm btn-danger-ghost" data-open="cancelModal"
                        data-fill='<?= h(json_encode(['id' => $a['id'], 'when' => nice_date($a['appt_date']) . ', ' . slot_label($a['slot_time'])])) ?>'>Cancel appointment</button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<aside class="family-card-wrap">
    <h2 class="section-title">Your family</h2>
    <div class="card family-card">
        <?php if (!$family): ?>
            <p class="muted small" style="margin:6px 0 4px">Add family members once and their details are filled in every time you book for them.</p>
        <?php endif; ?>
        <?php foreach ($family as $i => $d): $av = ['av-yellow', 'av-red', 'av-blue', 'av-green'][$i % 4]; ?>
            <div class="family-row">
                <span class="fam-avatar <?= $av ?>"><?= h(mb_strtoupper(mb_substr($d['first_name'], 0, 1))) ?></span>
                <span class="fam-text"><strong><?= h(full_name($d)) ?></strong><small><?= h($d['relationship']) ?><?= age_of($d['birth_date']) >= 60 ? ', senior' : '' ?></small></span>
                <a href="<?= h(url('patient/book.php')) ?>" class="small">Book</a>
            </div>
        <?php endforeach; ?>
        <a class="btn btn-dashed btn-block" href="<?= h(url('patient/dependent.php')) ?>">+ Add family member</a>
    </div>
</aside>
</div>

<div class="row-between history-head">
    <h2 class="section-title">History</h2>
    <nav class="filter-chips" aria-label="Filter history">
        <?php foreach ($HISTORY_FILTERS as $key => $label):
            $n = $key === '' ? count($history) : ($counts[$key] ?? 0); ?>
            <a href="<?= h(url('patient/bookings.php' . ($key !== '' ? '?show=' . $key : ''))) ?>" class="fchip<?= $show === $key ? ' on' : '' ?>"<?= $show === $key ? ' aria-current="true"' : '' ?>><?= h($label) ?> <span><?= $n ?></span></a>
        <?php endforeach; ?>
    </nav>
</div>
<div class="card">
<?php if (!$shownHistory): ?>
    <p class="empty"><?= $history ? 'Nothing here yet.' : 'No bookings yet.' ?></p>
<?php else: ?>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>Ref.</th><th>Patient</th><th>Service</th><th>Date</th><th>Time slot</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($shownHistory as $a): ?>
            <tr>
                <td class="mono"><?= h($a['queue_no']) ?></td>
                <td><?= h(full_name($a)) ?></td>
                <td><?= h($a['service']) ?></td>
                <td class="nowrap"><?= h(nice_date($a['appt_date'])) ?></td>
                <td class="nowrap"><?= h(slot_label($a['slot_time'])) ?></td>
                <td><?= status_badge($a['status'], $a['cancelled_by']) ?>
                    <?php if ($a['status'] === 'cancelled' && $a['cancel_reason']): ?><div class="small muted"><?= h($a['cancel_reason']) ?></div><?php endif; ?>
                </td>
                <td class="right">
                    <?php if (can_reschedule($a)): ?>
                        <a class="btn btn-sm btn-ghost" href="<?= h(url('patient/reschedule.php?from=' . $a['id'])) ?>">Reschedule</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</div>

<div class="modal" id="cancelModal" hidden>
    <div class="modal-box">
        <h3>Cancel this appointment?</h3>
        <p class="muted">Appointment on <span data-slot="when"></span>.</p>
        <form method="post" data-validate-form novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="id" data-slot="id">
            <div class="field">
                <label for="reason">Reason for cancelling <span class="req">*</span></label>
                <select id="reason" name="reason" data-validate="required">
                    <?= select_options(CANCEL_REASONS, '') ?>
                </select>
                <small class="field-error"></small>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" data-close>Keep appointment</button>
                <button type="submit" class="btn btn-danger">Cancel appointment</button>
            </div>
        </form>
    </div>
</div>

<?php if ($askResched): ?>
<div class="modal" id="reschedModal" data-autoopen>
    <div class="modal-box">
        <h3>Pick a new schedule?</h3>
        <p class="muted">You can choose a new date and time now. The same patient and booking details are used.</p>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" data-close>Not now</button>
            <a class="btn btn-primary" href="<?= h(url('patient/reschedule.php?from=' . $askResched['id'])) ?>">Choose new schedule</a>
        </div>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
