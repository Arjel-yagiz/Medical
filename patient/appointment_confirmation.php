<?php
$pageTitle = 'Appointment Confirmed'; $area = 'patient';
require_once __DIR__ . '/../includes/functions.php';
require_patient();
$uid = (int) $_SESSION['user_id'];
$st = $pdo->prepare("SELECT a.*, d.doctor_name, d.clinic_room, dp.department_name, u.name AS patient_name
    FROM appointments a JOIN doctors d ON d.doctor_id = a.doctor_id
    JOIN departments dp ON dp.department_id = d.department_id JOIN users u ON u.user_id = a.user_id
    WHERE a.appointment_id = ? AND a.user_id = ?");
$st->execute([(int) ($_GET['id'] ?? 0), $uid]);
$a = $st->fetch();
if (!$a) { flash('error', 'Appointment not found.'); redirect('/patient/my_appointments.php'); }
require __DIR__ . '/../includes/header.php';
?>
<div style="max-width:640px;margin:0 auto">
  <div class="alert alert-success"><b>Appointment booked successfully!</b> A notification was added to your account.</div>
  <div class="ticket"><span class="hint">Appointment No. / Queue Number</span><br><b><?= e($a['appointment_no']) ?></b>
    <br><span class="hint">Queue No. <?= str_pad((string) $a['slot_number'], 3, '0', STR_PAD_LEFT) ?></span></div>
  <div class="card summary" style="margin-top:16px"><dl>
    <dt>Patient</dt><dd><?= e($a['patient_name']) ?></dd>
    <dt>Department</dt><dd><?= e($a['department_name']) ?></dd>
    <dt>Doctor</dt><dd><?= e($a['doctor_name']) ?></dd>
    <dt>Date</dt><dd><?= e(fmt_date($a['appointment_date'])) ?></dd>
    <dt>Time</dt><dd><?= e(fmt_time($a['appointment_time'])) ?></dd>
    <dt>Slot Number</dt><dd><?= (int) $a['slot_number'] ?></dd>
    <?php if ($a['clinic_room']): ?><dt>Clinic room</dt><dd><?= e($a['clinic_room']) ?></dd><?php endif; ?>
    <dt>Status</dt><dd><?= status_badge($a['status']) ?></dd>
  </dl></div>
  <a class="btn" href="my_appointments.php">My Appointments</a>
  <a class="btn btn-outline" href="notifications.php">View Notifications</a>
  <a class="btn btn-outline" href="dashboard.php">Dashboard</a>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
