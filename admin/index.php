<?php
$pageTitle = 'Admin Dashboard'; $area = 'admin';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$made = generate_reminders($pdo);

$c = $pdo->query("SELECT
  (SELECT COUNT(*) FROM users) AS patients,
  (SELECT COUNT(*) FROM doctors) AS doctors,
  (SELECT COUNT(*) FROM departments) AS departments,
  (SELECT COUNT(*) FROM appointments WHERE status = 'Pending') AS pending,
  (SELECT COUNT(*) FROM appointments WHERE status = 'Confirmed' AND appointment_date >= CURDATE()) AS confirmed,
  (SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status IN ('Pending','Confirmed')) AS today")->fetch();

$recent = $pdo->query("SELECT a.*, u.name AS patient_name, d.doctor_name FROM appointments a
   JOIN users u ON u.user_id = a.user_id JOIN doctors d ON d.doctor_id = a.doctor_id
   ORDER BY a.created_at DESC, a.appointment_id DESC LIMIT 8")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<h1>Admin Dashboard</h1>
<p class="lead">Welcome, <?= e($_SESSION['admin_name']) ?>.</p>
<div class="grid g4" style="margin-bottom:18px">
  <div class="stat"><b><?= (int) $c['pending'] ?></b><span>Pending appointments</span></div>
  <div class="stat"><b><?= (int) $c['confirmed'] ?></b><span>Upcoming confirmed</span></div>
  <div class="stat"><b><?= (int) $c['today'] ?></b><span>Active today</span></div>
  <div class="stat"><b><?= (int) $c['patients'] ?></b><span>Registered patients</span></div>
  <div class="stat"><b><?= (int) $c['doctors'] ?></b><span>Doctors</span></div>
  <div class="stat"><b><?= (int) $c['departments'] ?></b><span>Departments</span></div>
</div>
<div class="card">
  <a class="btn" href="appointments.php">Manage Appointments</a>
  <a class="btn btn-outline" href="schedules.php">Manage Schedules</a>
  <a class="btn btn-outline" href="doctors.php">Manage Doctors</a>
  <a class="btn btn-outline" href="departments.php">Manage Departments</a>
</div>
<h2>Latest bookings</h2>
<div class="table-wrap"><table>
  <thead><tr><th>Appointment No.</th><th>Patient</th><th>Doctor</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach ($recent as $r): ?>
    <tr><td><?= e($r['appointment_no']) ?></td><td><?= e($r['patient_name']) ?></td><td><?= e($r['doctor_name']) ?></td>
      <td><?= e(fmt_date($r['appointment_date'])) ?></td><td><?= e(fmt_time($r['appointment_time'])) ?></td><td><?= status_badge($r['status']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$recent): ?><tr><td colspan="6" class="empty">No appointments yet.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
