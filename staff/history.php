<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('staff');

$f = [
    'q'          => mb_substr(input('q', $_GET), 0, 60),
    'visit_type' => input('visit_type', $_GET),
    'service'    => input('service', $_GET),
    'priority'   => input('priority', $_GET),
    'status'     => input('status', $_GET),
    'from'       => input('from', $_GET),
    'to'         => input('to', $_GET),
];
$STATUSES = ['upcoming' => 'Upcoming', 'completed' => 'Completed', 'missed' => 'Missed', 'cancelled' => 'Cancelled'];

$where = [];
$params = [];
if ($f['q'] !== '') {
    $where[] = "(CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) LIKE ? OR a.queue_no LIKE ? OR u.email LIKE ?)";
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $f['q']) . '%';
    array_push($params, $like, $like, $like);
}
if (in_array($f['visit_type'], VISIT_TYPES, true)) { $where[] = 'a.visit_type = ?'; $params[] = $f['visit_type']; } else $f['visit_type'] = '';
if (in_array($f['service'], SERVICES, true))       { $where[] = 'a.service = ?';    $params[] = $f['service']; }    else $f['service'] = '';
if (in_array($f['priority'], PRIORITIES, true))    { $where[] = 'a.priority = ?';   $params[] = $f['priority']; }   else $f['priority'] = '';
if (isset($STATUSES[$f['status']]))                { $where[] = 'a.status = ?';     $params[] = $f['status']; }     else $f['status'] = '';
if (valid_date_string($f['from']))                 { $where[] = 'a.appt_date >= ?'; $params[] = $f['from']; }       else $f['from'] = '';
if (valid_date_string($f['to']))                   { $where[] = 'a.appt_date <= ?'; $params[] = $f['to']; }         else $f['to'] = '';

$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$from = 'FROM appointments a JOIN patients p ON p.id = a.patient_id JOIN users u ON u.id = a.user_id';

$perPage = 20;
$total = (int)q("SELECT COUNT(*) $from $sqlWhere", $params)->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;

$rows = q("SELECT a.*, p.first_name, p.middle_name, p.last_name, p.suffix, u.email AS account_email
           $from $sqlWhere ORDER BY a.appt_date DESC, a.slot_time DESC LIMIT $perPage OFFSET $offset", $params)->fetchAll();

function page_link(int $p, array $f): string
{
    return url('staff/history.php?' . http_build_query(array_filter($f + ['page' => $p], fn($v) => $v !== '')));
}

$pageTitle = 'Appointment history';
$activeNav = 'history';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Appointment history</h1><p>Every booking, including cancelled and missed ones.</p></div>

<form method="get" class="card filters" data-validate-form novalidate>
    <div class="field grow">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= h($f['q']) ?>" maxlength="60" placeholder="Patient name, queue no., or email">
    </div>
    <div class="field">
        <label for="visit_type">Visit type</label>
        <select id="visit_type" name="visit_type"><option value="">All</option><?= select_options(VISIT_TYPES, $f['visit_type'], false) ?></select>
    </div>
    <div class="field">
        <label for="service">Service</label>
        <select id="service" name="service"><option value="">All</option><?= select_options(SERVICES, $f['service'], false) ?></select>
    </div>
    <div class="field">
        <label for="priority">Priority</label>
        <select id="priority" name="priority"><option value="">All</option><?= select_options(PRIORITIES, $f['priority'], false) ?></select>
    </div>
    <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status"><option value="">All</option><?= select_options($STATUSES, $f['status'], false) ?></select>
    </div>
    <div class="field">
        <label for="from">From</label>
        <input type="date" id="from" name="from" value="<?= h($f['from']) ?>">
    </div>
    <div class="field">
        <label for="to">To</label>
        <input type="date" id="to" name="to" value="<?= h($f['to']) ?>" data-validate="after:from">
        <small class="field-error"></small>
    </div>
    <div class="filter-actions">
        <button class="btn btn-primary" type="submit">Apply</button>
        <a class="btn btn-ghost" href="<?= h(url('staff/history.php')) ?>">Reset</a>
    </div>
</form>

<div class="card">
    <p class="muted small" style="margin-bottom:14px"><?= number_format($total) ?> <?= $total === 1 ? 'appointment' : 'appointments' ?></p>
    <?php if (!$rows): ?>
        <p class="empty">No appointments match these filters. <a href="<?= h(url('staff/history.php')) ?>">Clear filters</a></p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>Ref.</th><th>Patient</th><th>Visit type</th><th>Service</th><th>Priority</th><th>Date</th><th>Time slot</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $a): ?>
        <tr>
            <td class="mono"><?= h($a['queue_no']) ?></td>
            <td><?= h(full_name($a)) ?><div class="small muted"><?= h($a['account_email']) ?></div></td>
            <td><?= h($a['visit_type']) ?></td>
            <td><?= h($a['service']) ?></td>
            <td><?= priority_chip($a['priority']) ?></td>
            <td class="nowrap"><a href="<?= h(url('staff/index.php?month=' . substr($a['appt_date'], 0, 7) . '&date=' . $a['appt_date'])) ?>"><?= h(nice_date($a['appt_date'])) ?></a></td>
            <td class="nowrap"><?= h(slot_label($a['slot_time'])) ?></td>
            <td><?= status_badge($a['status'], $a['cancelled_by']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if ($pages > 1): ?>
    <nav class="pager">
        <?php if ($page > 1): ?><a class="btn btn-sm btn-ghost" href="<?= h(page_link($page - 1, $f)) ?>">Previous</a><?php endif; ?>
        <span>Page <?= $page ?> of <?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="btn btn-sm btn-ghost" href="<?= h(page_link($page + 1, $f)) ?>">Next</a><?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
