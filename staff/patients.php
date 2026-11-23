<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('staff');

$STATUSES = ['pending' => 'Waiting for approval', 'verified' => 'Verified', 'new' => 'Incomplete registration', 'deactivated' => 'Deactivated'];
$f = [
    'q'      => mb_substr(input('q', $_GET), 0, 60),
    'status' => input('status', $_GET),
];
if (!isset($STATUSES[$f['status']])) $f['status'] = '';

$where = ["u.role = 'patient'"];
$params = [];
if ($f['q'] !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $f['q']) . '%';
    $where[] = "(CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) LIKE ? OR p.philhealth_no LIKE ? OR u.email LIKE ?
                 OR p.barangay LIKE ? OR p.street LIKE ?)";
    array_push($params, $like, $like, $like, $like, $like);
}
if ($f['status'] === 'deactivated') {
    $where[] = 'u.is_active = 0';
} elseif ($f['status'] !== '') {
    $where[] = 'u.is_active = 1 AND u.approval_status = ?';
    $params[] = $f['status'];
}
$sqlWhere = 'WHERE ' . implode(' AND ', $where);
$from = 'FROM users u LEFT JOIN patients p ON p.user_id = u.id AND p.is_self = 1 AND p.is_deleted = 0';

$counts = [];
foreach (q("SELECT CASE WHEN is_active = 0 THEN 'deactivated' ELSE approval_status END AS s, COUNT(*) AS n
            FROM users WHERE role = 'patient' GROUP BY s")->fetchAll() as $r) $counts[$r['s']] = (int)$r['n'];

$perPage = 25;
$total = (int)q("SELECT COUNT(*) $from $sqlWhere", $params)->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;

// Waiting for approval first (oldest first), then everyone else by name
$rows = q("SELECT u.id AS uid, u.email, u.is_active, u.approval_status, u.submitted_at, p.*,
                  (SELECT COUNT(*) FROM patients d WHERE d.user_id = u.id AND d.is_self = 0 AND d.is_deleted = 0) AS dep_count
           $from $sqlWhere
           ORDER BY (u.is_active = 1 AND u.approval_status = 'pending') DESC, u.submitted_at ASC, p.last_name, p.first_name, u.email
           LIMIT $perPage OFFSET $offset", $params)->fetchAll();

function plink(int $p, array $f): string
{
    return url('staff/patients.php?' . http_build_query(array_filter($f + ['page' => $p], fn($v) => $v !== '')));
}

$pageTitle = 'Patient profiles';
$activeNav = 'patients';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Patient profiles</h1><p>Approve new accounts after checking the PhilHealth ID and that the patient lives in the barangay.</p></div>

<nav class="filter-chips" aria-label="Filter by status" style="margin-top:0">
    <a href="<?= h(url('staff/patients.php')) ?>" class="fchip<?= $f['status'] === '' ? ' on' : '' ?>">All <span><?= array_sum($counts) ?></span></a>
    <?php foreach ($STATUSES as $key => $label): ?>
        <a href="<?= h(url('staff/patients.php?status=' . $key)) ?>" class="fchip<?= $f['status'] === $key ? ' on' : '' ?>"><?= h($label) ?> <span><?= $counts[$key] ?? 0 ?></span></a>
    <?php endforeach; ?>
</nav>

<form method="get" class="card filters">
    <?php if ($f['status'] !== ''): ?><input type="hidden" name="status" value="<?= h($f['status']) ?>"><?php endif; ?>
    <div class="field grow">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= h($f['q']) ?>" maxlength="60" placeholder="Name, PhilHealth / YAKAP no., email, or address">
    </div>
    <div class="filter-actions">
        <button class="btn btn-primary" type="submit">Search</button>
        <a class="btn btn-ghost" href="<?= h(url('staff/patients.php')) ?>">Reset</a>
    </div>
</form>

<div class="card">
    <p class="muted small" style="margin-bottom:14px"><?= number_format($total) ?> account(s)</p>
    <?php if (!$rows): ?>
        <p class="empty">No patients match. <a href="<?= h(url('staff/patients.php')) ?>">Clear filters</a></p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>PhilHealth / YAKAP no.</th><th>Full name</th><th class="hide-sm">Address</th><th>Status</th><th class="right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $u = ['is_active' => $r['is_active'], 'approval_status' => $r['approval_status']]; ?>
            <tr>
                <td class="mono small"><?= $r['philhealth_no'] ? h($r['philhealth_no']) : '<span class="muted">-</span>' ?></td>
                <td>
                    <?= $r['first_name'] ? h(full_name($r)) : '<span class="muted">(no details yet)</span>' ?>
                    <div class="small muted"><?= h($r['email']) ?><?= $r['dep_count'] ? ' &middot; ' . (int)$r['dep_count'] . ' dependent(s)' : '' ?></div>
                </td>
                <td class="hide-sm small"><?= $r['first_name'] ? h($r['street'] . ', ' . $r['barangay'] . ', ' . $r['municipality']) : '' ?></td>
                <td><?= account_badge($u) ?></td>
                <td class="right nowrap">
                    <a class="btn btn-sm <?= ($r['approval_status'] === 'pending' && $r['is_active']) ? 'btn-primary' : 'btn-ghost' ?>"
                       href="<?= h(url('staff/patient.php?id=' . $r['uid'])) ?>"><?= ($r['approval_status'] === 'pending' && $r['is_active']) ? 'Review' : 'View' ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if ($pages > 1): ?>
    <nav class="pager">
        <?php if ($page > 1): ?><a class="btn btn-sm btn-ghost" href="<?= h(plink($page - 1, $f)) ?>">Previous</a><?php endif; ?>
        <span>Page <?= $page ?> of <?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="btn btn-sm btn-ghost" href="<?= h(plink($page + 1, $f)) ?>">Next</a><?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
