<?php
require __DIR__ . '/../includes/bootstrap.php';
$staff = require_role('staff');

const MAX_QTY = 100000;

function positive_int(string $v, int $min = 1): ?int
{
    if (!preg_match('/^\d{1,6}$/', $v)) return null;
    $n = (int)$v;
    return ($n >= $min && $n <= MAX_QTY) ? $n : null;
}

$addErrors = [];
$addVals = ['name' => '', 'in_stock' => '', 'critical_qty' => ''];

if (is_post()) {
    verify_csrf();
    $action = input('action');

    if ($action === 'restock') {
        $id = (int)input('id');
        $qty = positive_int(input('qty'));
        $med = q('SELECT * FROM medicines WHERE id = ?', [$id])->fetch();
        if (!$med) {
            flash('error', 'Medicine not found.');
        } elseif ($qty === null) {
            flash('error', 'Restock quantity must be a whole number from 1 to ' . number_format(MAX_QTY) . '.');
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            q('UPDATE medicines SET in_stock = in_stock + ? WHERE id = ?', [$qty, $id]);
            q('INSERT INTO restock_logs (medicine_id, quantity, staff_id) VALUES (?,?,?)', [$id, $qty, $staff['id']]);
            $pdo->commit();
            flash('success', 'Added ' . number_format($qty) . ' to ' . $med['name'] . '. New stock: ' . number_format($med['in_stock'] + $qty) . '.');
        }
        redirect('staff/inventory.php');
    }

    if ($action === 'add') {
        $addVals = ['name' => preg_replace('/\s+/', ' ', input('name')), 'in_stock' => input('in_stock'), 'critical_qty' => input('critical_qty')];
        if ($addVals['name'] === '' || mb_strlen($addVals['name']) < 2 || mb_strlen($addVals['name']) > 120) {
            $addErrors['name'] = 'Medicine name must be 2 to 120 characters.';
        } elseif (!preg_match('/^[\p{L}\p{N} .,%+\/()\-]+$/u', $addVals['name'])) {
            $addErrors['name'] = 'Name has invalid characters.';
        } elseif (q('SELECT COUNT(*) FROM medicines WHERE name = ?', [$addVals['name']])->fetchColumn() > 0) {
            $addErrors['name'] = 'This medicine is already in the list.';
        }
        $stock = positive_int($addVals['in_stock'], 0);
        $crit  = positive_int($addVals['critical_qty'], 0);
        if ($stock === null) $addErrors['in_stock'] = 'Whole number from 0 to ' . number_format(MAX_QTY) . '.';
        if ($crit === null)  $addErrors['critical_qty'] = 'Whole number from 0 to ' . number_format(MAX_QTY) . '.';

        if (!$addErrors) {
            q('INSERT INTO medicines (name, in_stock, critical_qty) VALUES (?,?,?)', [$addVals['name'], $stock, $crit]);
            flash('success', $addVals['name'] . ' added to inventory.');
            redirect('staff/inventory.php');
        }
    }
}

$meds = q('SELECT * FROM medicines ORDER BY name ASC')->fetchAll();

// Mini history: medicine given today (resets every day)
$today = q("SELECT d.quantity, d.created_at, m.name AS medicine, doc.name AS doctor, u.display_name AS staff_name, u.email AS staff_email,
                   p.first_name, p.middle_name, p.last_name, p.suffix, p.philhealth_no, p.is_self, p.dep_seq, p.user_id
            FROM dispensations d JOIN medicines m ON m.id = d.medicine_id JOIN doctors doc ON doc.id = d.doctor_id
            JOIN users u ON u.id = d.staff_id JOIN patients p ON p.id = d.patient_id
            WHERE d.created_at >= ? AND d.created_at < ? ORDER BY d.created_at DESC, d.id DESC",
            [date('Y-m-d') . ' 00:00:00', date('Y-m-d', strtotime('+1 day')) . ' 00:00:00'])->fetchAll();

function stock_level(array $m): string
{
    $stock = (int)$m['in_stock'];
    $crit = (int)$m['critical_qty'];
    if ($stock <= $crit) return 'low';
    if ($stock <= $crit * 1.5) return 'getting';
    return 'ok';
}
$LEVELS = ['low' => 'Low', 'getting' => 'Getting low', 'ok' => 'OK'];
$levelCounts = array_count_values(array_map('stock_level', $meds));
$criticalCount = $levelCounts['low'] ?? 0;
$show = input('show', $_GET);
if (!isset($LEVELS[$show])) $show = '';
$shownMeds = $show === '' ? $meds : array_values(array_filter($meds, fn($m) => stock_level($m) === $show));

$pageTitle = 'Medicine stock';
$activeNav = 'inventory';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head row-between">
    <div>
        <h1>Medicine stock</h1>
        <p><?= count($meds) ?> medicines.
            <?php if ($criticalCount): ?><span class="text-danger"><?= $criticalCount ?> at or below the critical quantity.</span><?php else: ?>All stock levels are above critical.<?php endif; ?></p>
    </div>
    <button type="button" class="btn btn-primary" data-open="addModal">Add medicine</button>
</div>

<nav class="filter-chips" aria-label="Filter by stock level" style="margin-top:0">
    <a href="<?= h(url('staff/inventory.php')) ?>" class="fchip<?= $show === '' ? ' on' : '' ?>">All <span><?= count($meds) ?></span></a>
    <?php foreach ($LEVELS as $key => $label): ?>
        <a href="<?= h(url('staff/inventory.php?show=' . $key)) ?>" class="fchip fchip-<?= $key ?><?= $show === $key ? ' on' : '' ?>"><?= h($label) ?> <span><?= $levelCounts[$key] ?? 0 ?></span></a>
    <?php endforeach; ?>
</nav>

<div class="card">
    <?php if (!$shownMeds): ?><p class="empty">No medicines in this group.</p><?php else: ?>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>Medicine</th><th style="width:30%">In stock</th><th class="right">Critical quantity</th><th>Status</th><th>Restock</th></tr></thead>
        <tbody>
        <?php foreach ($shownMeds as $m):
            $level = stock_level($m);
            $scale = max((int)$m['in_stock'], (int)$m['critical_qty'] * 4, 1);
            $pct = round((int)$m['in_stock'] / $scale * 100);
            $critPct = round((int)$m['critical_qty'] / $scale * 100); ?>
        <tr class="lvl-<?= $level ?>">
            <td><a class="med-name" href="<?= h(url('staff/medicine.php?id=' . $m['id'])) ?>" title="See who received this medicine"><strong><?= h($m['name']) ?></strong></a></td>
            <td>
                <div class="level">
                    <div class="level-track" aria-hidden="true">
                        <div class="level-fill" style="width: <?= $pct ?>%"></div>
                        <div class="level-crit" style="left: <?= $critPct ?>%"></div>
                    </div>
                    <span class="mono stock-num"><?= number_format((int)$m['in_stock']) ?></span>
                </div>
            </td>
            <td class="right mono"><?= number_format((int)$m['critical_qty']) ?></td>
            <td><span class="lvl-chip"><?= h($LEVELS[$level]) ?></span></td>
            <td>
                <form method="post" class="restock-form" novalidate
                      data-name="<?= h($m['name']) ?>" data-stock="<?= (int)$m['in_stock'] ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="restock">
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <div class="field inline-field">
                        <input type="text" name="qty" inputmode="numeric" maxlength="6" placeholder="Qty"
                               data-validate="required|posint" aria-label="Restock quantity for <?= h($m['name']) ?>">
                        <button type="submit" class="btn btn-sm <?= $level === 'low' ? 'btn-danger' : 'btn-primary' ?>">Add</button>
                    </div>
                    <small class="field-error"></small>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="muted small" style="margin:14px 0 0">The thin dark line on each bar marks the critical quantity. Click a medicine name to see who received it.</p>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head">
        <div>
            <h2>Given today</h2>
            <p class="muted small"><?= h(date('l, F j, Y')) ?>. This list starts fresh every day.</p>
        </div>
        <a class="btn btn-ghost" href="<?= h(url('staff/dispensed.php')) ?>">Print / other dates</a>
    </div>
    <?php if (!$today): ?>
        <p class="empty">No medicine given yet today.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>PhilHealth / YAKAP no.</th><th>Patient</th><th>Medicine</th><th class="right">Qty</th><th>Doctor</th><th>Distributed by</th><th>Time</th></tr></thead>
        <tbody>
        <?php foreach ($today as $g): ?>
            <tr>
                <td class="mono small"><?= h(patient_pid($g)) ?: '<span class="muted">-</span>' ?></td>
                <td><?= h(full_name($g)) ?></td>
                <td><?= h($g['medicine']) ?></td>
                <td class="right mono"><?= number_format((int)$g['quantity']) ?></td>
                <td><?= h($g['doctor']) ?></td>
                <td><?= h($g['staff_name'] ?: $g['staff_email']) ?></td>
                <td class="nowrap"><?= h(date('g:i A', strtotime($g['created_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<div class="modal" id="restockModal" hidden>
    <div class="modal-box">
        <h3>Confirm restock</h3>
        <p>Add <strong data-slot="qty"></strong> to <strong data-slot="name"></strong>?</p>
        <p class="muted">Stock goes from <span data-slot="old"></span> to <strong data-slot="new"></strong>. Check the amount before saving.</p>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" data-close>Edit amount</button>
            <button type="button" class="btn btn-primary" id="restockYes">Add to stock</button>
        </div>
    </div>
</div>

<div class="modal" id="addModal" <?= $addErrors ? 'data-autoopen' : 'hidden' ?>>
    <div class="modal-box">
        <h3>Add medicine</h3>
        <form method="post" data-validate-form novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="field">
                <label for="name">Medicine name &amp; strength <span class="req">*</span></label>
                <input type="text" id="name" name="name" maxlength="120" value="<?= h($addVals['name']) ?>" placeholder="e.g. Paracetamol 500mg"
                       data-validate="required|min:2|max:120" class="<?= invalid_class($addErrors, 'name') ?>">
                <?= field_error($addErrors, 'name') ?>
            </div>
            <div class="grid grid-2">
                <div class="field">
                    <label for="in_stock">Current stock <span class="req">*</span></label>
                    <input type="text" id="in_stock" name="in_stock" inputmode="numeric" maxlength="6" value="<?= h($addVals['in_stock']) ?>"
                           data-validate="required|wholenum" class="<?= invalid_class($addErrors, 'in_stock') ?>">
                    <?= field_error($addErrors, 'in_stock') ?>
                </div>
                <div class="field">
                    <label for="critical_qty">Critical quantity <span class="req">*</span></label>
                    <input type="text" id="critical_qty" name="critical_qty" inputmode="numeric" maxlength="6" value="<?= h($addVals['critical_qty']) ?>"
                           data-validate="required|wholenum" class="<?= invalid_class($addErrors, 'critical_qty') ?>">
                    <?= field_error($addErrors, 'critical_qty') ?>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" data-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Add medicine</button>
            </div>
        </form>
    </div>
</div>
<?php $pageScripts = ['inventory.js']; require __DIR__ . '/../includes/footer.php'; ?>
