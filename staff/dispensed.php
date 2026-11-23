<?php
require __DIR__ . '/../includes/bootstrap.php';
$staff = require_role('staff');

$date = input('date', $_GET);
if (!valid_date_string($date) || $date > date('Y-m-d')) $date = date('Y-m-d');

$rows = q("SELECT d.quantity, d.created_at, m.name AS medicine, doc.name AS doctor, u.display_name AS staff_name, u.email AS staff_email,
                  p.first_name, p.middle_name, p.last_name, p.suffix, p.philhealth_no, p.is_self, p.dep_seq, p.user_id,
                  holder.philhealth_no AS holder_no
           FROM dispensations d JOIN medicines m ON m.id = d.medicine_id JOIN doctors doc ON doc.id = d.doctor_id
           JOIN users u ON u.id = d.staff_id JOIN patients p ON p.id = d.patient_id
           LEFT JOIN patients holder ON holder.user_id = p.user_id AND holder.is_self = 1
           WHERE d.created_at >= ? AND d.created_at < ? ORDER BY d.created_at, d.id",
           [$date . ' 00:00:00', date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00'])->fetchAll();

$totals = [];
foreach ($rows as $r) $totals[$r['medicine']] = ($totals[$r['medicine']] ?? 0) + (int)$r['quantity'];
ksort($totals);

$pageTitle = 'Medicine given report';
$activeNav = 'inventory';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <a class="back no-print" href="<?= h(url('staff/inventory.php')) ?>">&larr; Medicine stock</a>
    <h1>Medicine given</h1>
    <p><?= h(APP_NAME) ?> &middot; <?= h(date('l, F j, Y', strtotime($date))) ?></p>
</div>

<form method="get" class="card filters no-print">
    <div class="field">
        <label for="date">Date</label>
        <input type="date" id="date" name="date" value="<?= h($date) ?>" max="<?= date('Y-m-d') ?>">
    </div>
    <div class="filter-actions">
        <button class="btn btn-primary" type="submit">Show</button>
        <button type="button" class="btn btn-ghost" onclick="window.print()">Print</button>
    </div>
</form>

<div class="card">
    <?php if (!$rows): ?>
        <p class="empty">No medicine was given on this date.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>Time</th><th>PhilHealth / YAKAP no.</th><th>Patient</th><th>Medicine</th><th class="right">Qty</th><th>Doctor</th><th>Distributed by</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $g): ?>
            <tr>
                <td class="nowrap"><?= h(date('g:i A', strtotime($g['created_at']))) ?></td>
                <td class="mono small"><?= h(patient_pid($g, (string)$g['holder_no'])) ?: '-' ?></td>
                <td><?= h(full_name($g)) ?></td>
                <td><?= h($g['medicine']) ?></td>
                <td class="right mono"><?= number_format((int)$g['quantity']) ?></td>
                <td><?= h($g['doctor']) ?></td>
                <td><?= h($g['staff_name'] ?: $g['staff_email']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <h3 class="subhead">Total per medicine</h3>
    <table class="table" style="max-width:480px">
        <tbody>
        <?php foreach ($totals as $name => $qty): ?>
            <tr><td><?= h($name) ?></td><td class="right mono"><?= number_format($qty) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="small muted" style="margin-top:18px">Printed by <?= h($staff['display_name'] ?: $staff['email']) ?> on <?= h(date('M j, Y g:i A')) ?></p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
