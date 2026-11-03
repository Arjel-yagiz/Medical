<?php
// Usage: $pageTitle = '...'; $area = 'public'|'patient'|'admin'|'doctor'; require header.php
require_once __DIR__ . '/functions.php';
$area = $area ?? 'public';
$pageTitle = $pageTitle ?? 'Online Medical Appointment System';
$isShell = ($area === 'patient' && is_patient()) || ($area === 'admin' && is_admin()) || ($area === 'doctor' && is_doctor());
$cur = basename($_SERVER['SCRIPT_NAME']);
$unread = 0; $recent = [];
if ($area === 'patient' && is_patient()) {
    $unread = unread_count($pdo, (int) $_SESSION['user_id']);
    $st = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, notification_id DESC LIMIT 5');
    $st->execute([(int) $_SESSION['user_id']]);
    $recent = $st->fetchAll();
}
$who = $area === 'admin' ? ($_SESSION['admin_name'] ?? 'Admin') : ($area === 'doctor' ? ($_SESSION['doctor_name'] ?? 'Doctor') : ($_SESSION['user_name'] ?? ''));
$roleLabel = ['admin' => 'Admin / Staff', 'doctor' => 'Doctor', 'patient' => 'Patient'][$area] ?? '';
$logoutHref = ['admin' => '/admin/logout.php', 'doctor' => '/doctor/logout.php', 'patient' => '/logout.php'][$area] ?? '';

if ($isShell) {
    $menu = [
        'patient' => [['👤', 'My profile', '/patient/profile.php'], ['🔔', 'Notifications', '/patient/notifications.php'], ['🎟️', 'Upcoming appointments', '/patient/my_appointments.php'], ['❓', 'Help center', '/help.php'], ['⚙️', 'All settings', '/settings.php']],
        'doctor'  => [['👤', 'My profile', '/doctor/profile.php'], ['🗓️', 'My schedule', '/doctor/schedule.php'], ['❓', 'Help center', '/help.php'], ['⚙️', 'All settings', '/settings.php']],
        'admin'   => [['👤', 'Account & password', '/settings.php'], ['🔔', 'Notifications', '/admin/notifications.php'], ['❓', 'Help center', '/help.php'], ['⚙️', 'All settings', '/settings.php']],
    ][$area];
} else {
    $menu = [['❓', 'Help center', '/help.php'], ['⚙️', 'All settings', '/settings.php'], ['🔑', 'Patient login', '/login.php'], ['🩺', 'Doctor login', '/doctor/login.php'], ['🛠️', 'Staff login', '/admin/login.php']];
}

if (!function_exists('nav_item')) {
    function nav_item(string $href, string $icon, string $label, string $cur, int $badge = 0): string {
        $on = basename($href) === $cur ? ' on' : '';
        return '<a class="nav-i' . $on . '" href="' . BASE_URL . $href . '"><span class="ni">' . $icon . '</span><span class="nl">' . e($label) . '</span>' .
               ($badge ? '<span class="pill">' . $badge . '</span>' : '') . '</a>';
    }
    /** Settings dropdown (top-right): profile, notifications, help, settings, dark mode, logout. */
    function gear_menu(array $menu, string $who, string $role, string $logout): string {
        $h = '<details class="dd" data-dd><summary class="icon-btn labeled" aria-label="Settings, profile, help and logout"><span aria-hidden="true">⚙️</span><span class="lbl">Settings</span></summary>' .
             '<div class="dd-panel" role="menu">';
        if ($who !== '') $h .= '<div class="dd-head"><b>' . e($who) . '</b><small>' . e($role) . '</small></div>';
        foreach ($menu as [$ic, $lb, $href]) $h .= '<a class="dd-item" role="menuitem" href="' . BASE_URL . $href . '"><span>' . $ic . '</span>' . e($lb) . '</a>';
        $h .= '<div class="dd-sep"></div><button type="button" class="dd-item" id="themeBtn" role="menuitem" aria-pressed="false"><span>🌓</span>Dark mode<i class="switch"></i></button>';
        if ($logout !== '') $h .= '<div class="dd-sep"></div><a class="dd-item danger" role="menuitem" href="' . BASE_URL . $logout . '"><span>🚪</span>Log out</a>';
        return $h . '</div></details>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | Omega&amp;Care</title>
<script>try{var d=document.documentElement,L=localStorage,t=L.getItem('qc-theme');if(t==='system')t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';if(t)d.dataset.theme=t;if(L.getItem('qc-size'))d.dataset.size=L.getItem('qc-size');if(L.getItem('qc-motion'))d.dataset.motion=L.getItem('qc-motion');}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/menu-fix.css">
</head>
<body class="area-<?= e($area) ?><?= $isShell ? ' has-shell' : '' ?>">
<?php if ($isShell): ?>
<div class="app">
  <aside class="side">
    <a class="logo" href="<?= BASE_URL ?>/index.php"><span class="logo-mark">Ω</span><span class="logo-t">Omega<i>&amp;</i>Care</span></a>
    <nav class="side-nav">
    <?php if ($area === 'doctor'):
        echo nav_item('/doctor/index.php', '🏠', 'Today', $cur), nav_item('/doctor/appointments.php', '📋', 'Appointments', $cur),
             nav_item('/doctor/schedule.php', '🗓️', 'Schedule', $cur), nav_item('/doctor/profile.php', '👤', 'Profile', $cur);
    elseif ($area === 'admin'):
        echo nav_item('/admin/index.php', '📊', 'Overview', $cur), nav_item('/admin/appointments.php', '📋', 'Appointments', $cur),
             nav_item('/admin/schedules.php', '🗓️', 'Schedules', $cur), nav_item('/admin/doctors.php', '🩺', 'Doctors', $cur),
             nav_item('/admin/departments.php', '🏥', 'Departments', $cur), nav_item('/admin/notifications.php', '🔔', 'Notifications', $cur);
    else:
        echo nav_item('/patient/dashboard.php', '🏠', 'Overview', $cur), nav_item('/patient/book.php', '➕', 'Book', $cur),
             nav_item('/patient/my_appointments.php', '🎟️', 'Upcoming', $cur), nav_item('/patient/history.php', '🕘', 'History', $cur),
             nav_item('/patient/notifications.php', '🔔', 'Alerts', $cur, $unread), nav_item('/patient/profile.php', '👤', 'Profile', $cur);
    endif; ?>
    </nav>
    <div class="side-foot">
      <span class="who"><?= e($who) ?></span>
      <div class="side-links">
        <?php if ($area !== 'admin'): ?><a href="<?= BASE_URL ?>/<?= e($area) ?>/profile.php">👤 Profile</a><?php endif; ?>
        <a href="<?= BASE_URL ?>/settings.php">⚙️ Settings</a>
        <a href="<?= BASE_URL ?>/help.php">❓ Help</a>
        <a href="<?= BASE_URL . $logoutHref ?>">🚪 Log out</a>
      </div>
    </div>
  </aside>
  <div class="content">
    <div class="utilbar">
      <span class="util-title"><?= e($pageTitle) ?></span>
      <div class="util">
      <?php if ($area === 'patient'): ?>
        <details class="dd" data-dd>
          <summary class="icon-btn<?= $unread ? ' has-new' : '' ?>" aria-label="Notifications (<?= $unread ?> unread)">
            <span aria-hidden="true">🔔</span><?php if ($unread): ?><b class="count"><?= $unread ?></b><?php endif; ?></summary>
          <div class="dd-panel wide" role="menu">
            <div class="dd-head"><b>Notifications</b><small><?= $unread ?> unread</small></div>
            <?php foreach ($recent as $n): ?>
              <a class="dd-note<?= $n['is_read'] ? '' : ' unread' ?>" href="<?= BASE_URL ?>/patient/notifications.php"><span class="dot"></span>
                <span><b><?= e($n['title']) ?></b><small><?= e(mb_strimwidth($n['message'], 0, 90, '...')) ?></small></span></a>
            <?php endforeach; ?>
            <?php if (!$recent): ?><p class="hint" style="padding:10px 12px;margin:0">Nothing yet.</p><?php endif; ?>
            <div class="dd-sep"></div>
            <div class="dd-foot">
              <a href="<?= BASE_URL ?>/patient/notifications.php">View all</a>
              <?php if ($unread): ?><form method="post" action="<?= BASE_URL ?>/patient/notifications.php"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button type="submit">Mark all read</button></form><?php endif; ?>
            </div>
          </div>
        </details>
      <?php endif; ?>
      <?php if ($area === 'admin'): ?><a class="icon-btn" href="<?= BASE_URL ?>/admin/notifications.php" title="Notifications" aria-label="Notifications"><span aria-hidden="true">🔔</span></a><?php endif; ?>
      <a class="icon-btn" href="<?= BASE_URL ?>/help.php" title="Help" aria-label="Help"><span aria-hidden="true">❓</span></a>
      <?= gear_menu($menu, $who, $roleLabel, $logoutHref) ?>
      </div>
    </div>
    <main class="page wrap">
<?php else: ?>
<header class="top">
  <div class="container nav">
    <a class="logo" href="<?= BASE_URL ?>/index.php"><span class="logo-mark">Ω</span><span class="logo-t">Omega<i>&amp;</i>Care</span></a>
    <nav class="menu">
      <a href="<?= BASE_URL ?>/index.php">Home</a>
      <a href="<?= BASE_URL ?>/login.php">Login</a>
      <a class="btn btn-sm" href="<?= BASE_URL ?>/register.php">Register</a>
      <?= gear_menu($menu, '', '', '') ?>
    </nav>
  </div>
</header>
<main class="container page">
<?php endif; ?>
<div id="toasts" class="toasts" aria-live="polite"></div>
<?php show_flash(); ?>
