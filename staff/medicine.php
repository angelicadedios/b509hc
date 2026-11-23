<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('staff');

$med = q('SELECT * FROM medicines WHERE id = ?', [(int)($_GET['id'] ?? 0)])->fetch();
if (!$med) {
    flash('error', 'Medicine not found.');
    redirect('staff/inventory.php');
}

$RANGES = ['today' => 'Today', '7' => 'Last 7 days', '14' => 'Last 14 days', '30' => 'Last 30 days', '90' => 'Last 90 days', 'all' => 'All time'];
$range = input('range', $_GET);
if (!isset($RANGES[$range])) $range = '30';
$search = mb_substr(input('q', $_GET), 0, 60);

$where = ['d.medicine_id = ?'];
$params = [$med['id']];
if ($range === 'today') {
    $where[] = 'd.created_at >= ?'; $params[] = date('Y-m-d') . ' 00:00:00';
} elseif ($range !== 'all') {
    $where[] = 'd.created_at >= ?'; $params[] = date('Y-m-d', strtotime('-' . ((int)$range - 1) . ' days')) . ' 00:00:00';
}
if ($search !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search) . '%';
    $where[] = "(CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) LIKE ? OR p.philhealth_no LIKE ? OR holder.philhealth_no LIKE ?)";
    array_push($params, $like, $like, $like);
}

$rows = q('SELECT d.quantity, d.created_at, doc.name AS doctor, u.display_name AS staff_name, u.email AS staff_email,
                  p.first_name, p.middle_name, p.last_name, p.suffix, p.philhealth_no, p.is_self, p.dep_seq, p.user_id,
                  holder.philhealth_no AS holder_no
           FROM dispensations d
           JOIN patients p ON p.id = d.patient_id
           LEFT JOIN patients holder ON holder.user_id = p.user_id AND holder.is_self = 1
           JOIN doctors doc ON doc.id = d.doctor_id
           JOIN users u ON u.id = d.staff_id
           WHERE ' . implode(' AND ', $where) . ' ORDER BY d.created_at DESC, d.id DESC LIMIT 500', $params)->fetchAll();
$totalQty = array_sum(array_column($rows, 'quantity'));

$restocks = q('SELECT r.quantity, r.created_at, u.display_name, u.email FROM restock_logs r JOIN users u ON u.id = r.staff_id
               WHERE r.medicine_id = ? ORDER BY r.created_at DESC LIMIT 10', [$med['id']])->fetchAll();

$pageTitle = $med['name'];
$activeNav = 'inventory';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <a class="back no-print" href="<?= h(url('staff/inventory.php')) ?>">&larr; Medicine stock</a>
    <h1><?= h($med['name']) ?></h1>
    <p>In stock: <strong><?= number_format((int)$med['in_stock']) ?></strong> &middot; Critical quantity: <?= number_format((int)$med['critical_qty']) ?>
        <?php if ((int)$med['in_stock'] <= (int)$med['critical_qty']): ?> &middot; <span class="text-danger">Needs restock</span><?php endif; ?></p>
</div>

<form method="get" class="card filters no-print">
    <input type="hidden" name="id" value="<?= (int)$med['id'] ?>">
    <div class="field grow">
        <label for="q">Search patient</label>
        <input type="search" id="q" name="q" value="<?= h($search) ?>" maxlength="60" placeholder="Name or PhilHealth / YAKAP number">
    </div>
    <div class="field">
        <label for="range">Date</label>
        <select id="range" name="range"><?= select_options($RANGES, $range, false) ?></select>
    </div>
    <div class="filter-actions">
        <button class="btn btn-primary" type="submit">Apply</button>
        <a class="btn btn-ghost" href="<?= h(url('staff/medicine.php?id=' . $med['id'])) ?>">Reset</a>
    </div>
</form>

<div class="card">
    <div class="card-head">
        <div>
            <h2>Given to patients</h2>
            <p class="muted small"><?= h($RANGES[$range]) ?>: <?= count($rows) ?> record(s), <?= number_format($totalQty) ?> total quantity</p>
        </div>
        <button type="button" class="btn btn-ghost no-print" onclick="window.print()">Print</button>
    </div>
    <?php if (!$rows): ?>
        <p class="empty">No records for this period.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>PhilHealth / YAKAP no.</th><th>Patient full name</th><th class="right">Qty</th><th>Doctor</th><th>Distributed by</th><th>Date and time</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $g): ?>
            <tr>
                <td class="mono small"><?= h(patient_pid($g, (string)$g['holder_no'])) ?: '<span class="muted">-</span>' ?></td>
                <td><?= h(full_name($g)) ?></td>
                <td class="right mono"><?= number_format((int)$g['quantity']) ?></td>
                <td><?= h($g['doctor']) ?></td>
                <td><?= h($g['staff_name'] ?: $g['staff_email']) ?></td>
                <td class="nowrap"><?= h(date('M j, Y g:i A', strtotime($g['created_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php if ($restocks): ?>
<div class="card no-print">
    <h2>Recent restocks</h2>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th class="right">Added</th><th>By</th><th>Date and time</th></tr></thead>
        <tbody>
        <?php foreach ($restocks as $r): ?>
            <tr><td class="right mono">+<?= number_format((int)$r['quantity']) ?></td><td><?= h($r['display_name'] ?: $r['email']) ?></td>
                <td><?= h(date('M j, Y g:i A', strtotime($r['created_at']))) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
