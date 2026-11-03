<?php
$pageTitle = 'Dashboard'; $area = 'patient';
require_once __DIR__ . '/../includes/functions.php';
require_patient();
$uid = (int) $_SESSION['user_id'];
generate_reminders($pdo);

$st = $pdo->prepare("SELECT
   SUM(status IN ('Pending','Confirmed') AND appointment_date >= CURDATE()) AS upcoming,
   SUM(status = 'Completed') AS completed,
   COUNT(*) AS total FROM appointments WHERE user_id = ?");
$st->execute([$uid]);
$s = $st->fetch();

$st = $pdo->prepare("SELECT a.*, d.doctor_name, dp.department_name FROM appointments a
   JOIN doctors d ON d.doctor_id = a.doctor_id JOIN departments dp ON dp.department_id = d.department_id
   WHERE a.user_id = ? AND a.status IN ('Pending','Confirmed') AND a.appointment_date >= CURDATE()
   ORDER BY a.appointment_date, a.appointment_time LIMIT 1");
$st->execute([$uid]);
$next = $st->fetch();

$st = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, notification_id DESC LIMIT 3');
$st->execute([$uid]);
$notifs = $st->fetchAll();
$unread = unread_count($pdo, $uid);

require __DIR__ . '/../includes/header.php';
?>
<h1>Welcome, <?= e($_SESSION['user_name']) ?></h1>
<p class="lead">Manage your appointments from one place.</p>

<div class="grid g4" style="margin-bottom:18px">
  <div class="stat"><b><?= (int) $s['upcoming'] ?></b><span>Upcoming appointments</span></div>
  <div class="stat"><b><?= (int) $s['completed'] ?></b><span>Completed visits</span></div>
  <div class="stat"><b><?= (int) $s['total'] ?></b><span>Total bookings</span></div>
  <div class="stat"><b>&#128276; <?= $unread ?></b><span>Unread notifications</span></div>
</div>

<div class="grid g2">
  <div class="card">
    <h2>Next appointment</h2>
    <?php if ($next): ?>
      <div class="summary"><dl>
        <dt>Appointment No.</dt><dd><?= e($next['appointment_no']) ?></dd>
        <dt>Doctor</dt><dd><?= e($next['doctor_name']) ?></dd>
        <dt>Department</dt><dd><?= e($next['department_name']) ?></dd>
        <dt>Date &amp; time</dt><dd><?= e(fmt_date($next['appointment_date'])) ?>, <?= e(fmt_time($next['appointment_time'])) ?></dd>
        <dt>Status</dt><dd><?= status_badge($next['status']) ?></dd>
      </dl></div>
    <?php else: ?>
      <p class="hint">You have no upcoming appointments.</p>
      <a class="btn" href="book.php">Book Appointment</a>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2>Recent notifications</h2>
    <?php foreach ($notifs as $n): ?>
      <div class="notif <?= $n['is_read'] ? 'read' : 'unread' ?>"><span class="dot"></span>
        <div><?= e($n['title']) ?><small><?= e($n['message']) ?></small></div></div>
    <?php endforeach; ?>
    <?php if (!$notifs): ?><p class="hint">No notifications yet.</p><?php endif; ?>
    <a href="notifications.php">View all &rarr;</a>
  </div>
</div>

<div class="card">
  <h2>Quick links</h2>
  <a class="btn" href="book.php">Book Appointment</a>
  <a class="btn btn-outline" href="my_appointments.php">My Appointments</a>
  <a class="btn btn-outline" href="history.php">Appointment History</a>
  <a class="btn btn-outline" href="profile.php">Profile</a>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
