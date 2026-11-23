<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('patient');
$uid = (int)$user['id'];

$items = q('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 50', [$uid])->fetchAll();
q('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$uid]);

$pageTitle = 'Notifications';
$activeNav = 'notifications';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Notifications</h1></div>
<div class="card">
<?php if (!$items): ?>
    <p class="empty">No notifications. You'll be notified here if the health center changes your appointment.</p>
<?php else: ?>
    <ul class="notif-list">
    <?php foreach ($items as $n): ?>
        <li class="<?= $n['is_read'] ? '' : 'unread' ?>">
            <div><?= h($n['message']) ?></div>
            <div class="small muted"><?= h(date('M j, Y g:i A', strtotime($n['created_at']))) ?>
                <?php if ($n['link']): ?> &middot; <a href="<?= h(url($n['link'])) ?>">View</a><?php endif; ?>
            </div>
        </li>
    <?php endforeach; ?>
    </ul>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
