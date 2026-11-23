<?php
$__user = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
$activeNav = $activeNav ?? '';

function nav_icon(string $name): string
{
    $paths = [
        'book'      => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M12 14v4M10 16h4"/>',
        'bookings'  => '<path d="M9 5h11M9 12h11M9 19h11"/><circle cx="4.5" cy="5" r="1.5"/><circle cx="4.5" cy="12" r="1.5"/><circle cx="4.5" cy="19" r="1.5"/>',
        'profile'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/>',
        'bell'      => '<path d="M6 9a6 6 0 0 1 12 0c0 6 2.5 7.5 2.5 7.5h-17S6 15 6 9"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'calendar'  => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><circle cx="8" cy="15" r="1"/><circle cx="12" cy="15" r="1"/><circle cx="16" cy="15" r="1"/>',
        'history'   => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
        'inventory' => '<path d="M10.5 3.5l-7 7a4.95 4.95 0 0 0 7 7l7-7a4.95 4.95 0 0 0-7-7z"/><path d="M7 7l7 7"/>',
        'patients'  => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2.5"/><path d="M5 17c.7-1.8 2.2-2.8 4-2.8s3.3 1 4 2.8M15 9h3M15 13h3"/>',
        'doctors'   => '<path d="M6 3v6a4 4 0 0 0 8 0V3"/><path d="M10 13v2a5 5 0 0 0 10 0v-2"/><circle cx="20" cy="11" r="2"/>',
        'password'  => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'staff'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18.5 14.5c1.9.8 3 2.7 3 5.5"/>',
        'logout'    => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H4"/>',
    ];
    return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

function side_link(string $href, string $label, string $key, string $active, string $extra = ''): string
{
    $cls = $key === $active ? ' class="active"' : '';
    $icon = nav_icon($key === 'notifications' ? 'bell' : $key);
    return '<a href="' . h(url($href)) . '"' . $cls . '>' . $icon . '<span>' . h($label) . '</span>' . $extra . '</a>';
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> | <?= h(APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="<?= h(url('assets/img/favicon.png')) ?>">
    <link rel="apple-touch-icon" href="<?= h(url('assets/img/apple-touch-icon.png')) ?>">
    <meta name="theme-color" content="#2b3090">
    <link rel="stylesheet" href="<?= h(url('assets/css/style.css')) ?>">
    <script>window.APP_BASE = <?= json_encode(base_path()) ?>;</script>
</head>
<body class="<?= $__user ? 'has-sidebar' : 'no-sidebar' ?>">

<?php if ($__user): ?>

<aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= h(url('index.php')) ?>">
        <img class="brand-logo" src="<?= h(url('assets/img/logo.png')) ?>" alt="" width="40" height="40">
        <span><strong>Barangay 509</strong><small>Health Center</small></span>
    </a>

    <nav class="side-nav" aria-label="Main menu">
        <?php if ($__user['role'] === 'patient'):
            $n = unread_count((int)$__user['id']); ?>
            <?= side_link('patient/book.php', 'Book appointment', 'book', $activeNav) ?>
            <?= side_link('patient/bookings.php', 'My bookings', 'bookings', $activeNav) ?>
            <?= side_link('patient/notifications.php', 'Notifications', 'notifications', $activeNav,
                $n > 0 ? '<span class="pill">' . $n . '</span>' : '') ?>
            <p class="side-label">Account</p>
            <?= side_link('patient/profile.php', 'Profile & dependents', 'profile', $activeNav) ?>
        <?php else: ?>
            <?= side_link('staff/index.php', 'Appointments', 'calendar', $activeNav) ?>
            <?= side_link('staff/history.php', 'History', 'history', $activeNav) ?>
            <?= side_link('staff/inventory.php', 'Medicine stock', 'inventory', $activeNav) ?>
            <?php $pend = pending_accounts_count(); ?>
            <?= side_link('staff/patients.php', 'Patient profiles', 'patients', $activeNav,
                $pend > 0 ? '<span class="pill" title="Waiting for approval">' . $pend . '</span>' : '') ?>
            <?php if ((int)$__user['is_admin']): ?>
                <p class="side-label">Administration</p>
                <?= side_link('staff/manage.php', 'Staff accounts', 'staff', $activeNav) ?>
                <?= side_link('staff/doctors.php', 'Doctors', 'doctors', $activeNav) ?>
            <?php endif; ?>
            <p class="side-label">Account</p>
            <?= side_link('staff/password.php', 'Change password', 'password', $activeNav) ?>
        <?php endif; ?>
    </nav>

    <div class="side-user">
        <span class="avatar"><?= h(mb_strtoupper(mb_substr(preg_replace('/^(Dr|Dra|Mr|Ms|Mrs)\.?\s+/i', '', $__user['display_name'] !== '' ? $__user['display_name'] : $__user['email']), 0, 1))) ?></span>
        <span class="side-user-text">
            <strong><?= h($__user['display_name'] !== '' ? $__user['display_name'] : ($__user['role'] === 'staff' ? 'Health center staff' : 'Patient')) ?></strong>
            <small><?= h($__user['email']) ?></small>
        </span>
    </div>
    <form method="post" action="<?= h(url('logout.php')) ?>" class="side-logout">
        <?= csrf_field() ?>
        <button type="submit"><?= nav_icon('logout') ?><span>Log out</span></button>
    </form>
</aside>
<div class="sidebar-backdrop" onclick="document.body.classList.remove('nav-open')"></div>

<div class="main-area">
    <header class="mobilebar">
        <button class="nav-toggle" type="button" aria-label="Open menu" onclick="document.body.classList.toggle('nav-open')">&#9776;</button>
        <img class="brand-logo" src="<?= h(url('assets/img/logo.png')) ?>" alt="" width="32" height="32" style="width:32px;height:32px">
        <strong><?= h($pageTitle) ?></strong>
    </header>
<?php else: ?>

<header class="topbar">
    <div class="container topbar-inner">
        <a class="brand" href="<?= h(url('index.php')) ?>">
            <img class="brand-logo" src="<?= h(url('assets/img/logo.png')) ?>" alt="" width="40" height="40">
            <span><strong>Barangay 509</strong><small>Health Center</small></span>
        </a>
        <nav class="top-links" aria-label="Main">
            <a class="hide-sm" href="<?= h(url('index.php#how')) ?>">How it works</a>
            <a class="hide-sm" href="<?= h(url('index.php#hours')) ?>">Clinic hours</a>
            <a href="<?= h(url('staff_login.php')) ?>">Staff sign-in</a>
        </nav>
    </div>
</header>
<div class="main-area">
<?php endif; ?>

<?php $mainClass = $mainClass ?? 'container page'; $__flashes = take_flashes(); ?>
<main class="<?= h($mainClass) ?>">
<?php if ($__flashes): ?>
<?= strpos($mainClass, 'container') === false ? '<div class="container flash-wrap">' : '' ?>
<?php foreach ($__flashes as $f): ?>
    <div class="alert alert-<?= h($f['type']) ?>"><?= h($f['msg']) ?></div>
<?php endforeach; ?>
<?= strpos($mainClass, 'container') === false ? '</div>' : '' ?>
<?php endif; ?>
