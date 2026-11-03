<?php
$pageTitle = 'Notifications'; $area = 'patient';
require_once __DIR__ . '/../includes/functions.php';
require_patient();
$uid = (int) $_SESSION['user_id'];
generate_reminders($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('action') === 'read_all') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$uid]);
    } elseif (post('action') === 'read_one') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?')->execute([(int) post('id'), $uid]);
    }
    redirect('/patient/notifications.php');
}

$st = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, notification_id DESC LIMIT 100');
$st->execute([$uid]);
$list = $st->fetchAll();
$unread = unread_count($pdo, $uid);
require __DIR__ . '/../includes/header.php';
?>
<h1>&#128276; Notifications (<?= $unread ?>)</h1>
<p class="lead">Unread notifications are highlighted.</p>
<?php if ($unread): ?>
<form method="post" style="margin-bottom:14px"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn-sm btn-outline">Mark all as read</button></form>
<?php endif; ?>
<?php foreach ($list as $n): ?>
  <div class="notif <?= $n['is_read'] ? 'read' : 'unread' ?>">
    <span class="dot"></span>
    <div style="flex:1"><?= e($n['title']) ?> <span class="badge badge-muted"><?= e($n['type']) ?></span>
      <small><?= e($n['message']) ?></small><small><?= e(date('M j, Y g:i A', strtotime($n['created_at']))) ?></small></div>
    <?php if (!$n['is_read']): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_one"><input type="hidden" name="id" value="<?= (int) $n['notification_id'] ?>">
      <button class="btn btn-sm btn-light" style="border:1px solid var(--line)">Mark read</button></form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php if (!$list): ?><p class="empty">No notifications yet.</p><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
