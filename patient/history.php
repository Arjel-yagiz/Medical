<?php
$pageTitle = 'Appointment History'; $area = 'patient';
require_once __DIR__ . '/../includes/functions.php';
require_patient();
$st = $pdo->prepare("SELECT a.*, d.doctor_name, dp.department_name FROM appointments a
    JOIN doctors d ON d.doctor_id = a.doctor_id JOIN departments dp ON dp.department_id = d.department_id
    WHERE a.user_id = ? AND (a.status IN ('Completed','Cancelled','No Show') OR a.appointment_date < CURDATE())
    ORDER BY a.appointment_date DESC, a.appointment_time DESC");
$st->execute([(int) $_SESSION['user_id']]);
$rows = $st->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<h1>Appointment History</h1>
<p class="lead">Completed, cancelled, missed and past appointments.</p>
<div class="table-wrap"><table>
  <thead><tr><th>Appointment No.</th><th>Department</th><th>Doctor</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= e($r['appointment_no']) ?></td><td><?= e($r['department_name']) ?></td><td><?= e($r['doctor_name']) ?></td>
      <td><?= e(fmt_date($r['appointment_date'])) ?></td><td><?= e(fmt_time($r['appointment_time'])) ?></td><td><?= status_badge($r['status']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="empty">No history yet.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
