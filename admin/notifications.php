<?php
$pageTitle = 'Manage Notifications'; $area = 'admin';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'reminders') {
        $n = generate_reminders($pdo);
        flash('success', "$n reminder(s) created for tomorrow's appointments.");
    } elseif ($action === 'send') {
        $uid = (int) post('user_id'); $title = mb_substr(post('title'), 0, 100); $msg = mb_substr(post('message'), 0, 500);
        $chk = $pdo->prepare('SELECT COUNT(*) FROM users WHERE user_id = ?'); $chk->execute([$uid]);
        if ($title === '' || $msg === '' || !$chk->fetchColumn()) flash('error', 'Select a patient and enter a title and message.');
        else { notify($pdo, $uid, null, 'Info', $title, $msg); flash('success', 'Notification sent.'); }
    }
    redirect('/admin/notifications.php');
}
$patients = $pdo->query('SELECT user_id, name, patient_id FROM users ORDER BY name')->fetchAll();
$rows = $pdo->query('SELECT n.*, u.name FROM notifications n JOIN users u ON u.user_id = n.user_id ORDER BY n.created_at DESC, n.notification_id DESC LIMIT 100')->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<h1>Manage Notifications</h1>
<div class="grid g2">
  <div class="card">
    <h2>Appointment reminders</h2>
    <p class="hint">Creates a "tomorrow" reminder for every Pending/Confirmed appointment dated tomorrow (once per appointment). Reminders are also generated automatically when patients/admins open their dashboards.</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reminders"><button class="btn">Send tomorrow's reminders</button></form>
  </div>
  <div class="card">
    <h2>Send a message to a patient</h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send">
      <label>Patient</label><select name="user_id" required><option value="">-- Select --</option>
        <?php foreach ($patients as $p): ?><option value="<?= (int) $p['user_id'] ?>"><?= e($p['name']) ?> (<?= e($p['patient_id']) ?>)</option><?php endforeach; ?></select>
      <label>Title</label><input name="title" maxlength="100" required>
      <label>Message</label><textarea name="message" rows="2" maxlength="500" required></textarea>
      <p><button class="btn">Send</button></p>
    </form>
  </div>
</div>
<h2>Recent notifications</h2>
<div class="table-wrap"><table>
  <thead><tr><th>When</th><th>Patient</th><th>Type</th><th>Title</th><th>Message</th><th>Read?</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $n): ?>
    <tr><td><?= e(date('M j, g:i A', strtotime($n['created_at']))) ?></td><td><?= e($n['name']) ?></td><td><span class="badge badge-muted"><?= e($n['type']) ?></span></td>
      <td><?= e($n['title']) ?></td><td><?= e($n['message']) ?></td><td><?= $n['is_read'] ? '<span class="badge badge-ok">Read</span>' : '<span class="badge badge-warn">Unread</span>' ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="empty">No notifications yet.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
