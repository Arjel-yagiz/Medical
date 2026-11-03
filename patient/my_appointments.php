<?php
$pageTitle = 'My Appointments'; $area = 'patient';
require_once __DIR__ . '/../includes/functions.php';
require_patient();
$uid = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['appointment_id'] ?? 0);
    $st = $pdo->prepare("SELECT appointment_id FROM appointments WHERE appointment_id = ? AND user_id = ? AND appointment_date >= CURDATE()");
    $st->execute([$id, $uid]);
    if ($st->fetch() && cancel_appointment($pdo, $id, 'patient')) flash('success', 'Your appointment was cancelled.');
    else flash('error', 'This appointment cannot be cancelled.');
    redirect('/patient/my_appointments.php');
}

$st = $pdo->prepare("SELECT a.*, d.doctor_name, dp.department_name FROM appointments a
    JOIN doctors d ON d.doctor_id = a.doctor_id JOIN departments dp ON dp.department_id = d.department_id
    WHERE a.user_id = ? AND a.status IN ('Pending','Confirmed') AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date, a.appointment_time");
$st->execute([$uid]);
$rows = $st->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<h1>My Appointments</h1>
<p class="lead">Upcoming appointments. Past and cancelled ones are in <a href="history.php">History</a>.</p>
<div class="table-wrap"><table>
  <thead><tr><th>Appointment No.</th><th>Department</th><th>Doctor</th><th>Date</th><th>Time</th><th>Slot</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><b><?= e($r['appointment_no']) ?></b></td><td><?= e($r['department_name']) ?></td><td><?= e($r['doctor_name']) ?></td>
      <td><?= e(fmt_date($r['appointment_date'])) ?></td><td><?= e(fmt_time($r['appointment_time'])) ?></td>
      <td><?= (int) $r['slot_number'] ?></td><td><?= status_badge($r['status']) ?></td>
      <td><form method="post" class="inline-form" onsubmit="return confirm('Cancel this appointment?')">
        <?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= (int) $r['appointment_id'] ?>">
        <button class="btn btn-sm btn-danger">Cancel</button></form></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="empty">No upcoming appointments. <a href="book.php">Book one now</a>.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
