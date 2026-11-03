<?php
$pageTitle = 'Today'; $area = 'doctor';
require_once __DIR__ . '/../includes/functions.php';
require_doctor();
$did = (int) $_SESSION['doctor_id'];

$st = $pdo->prepare("SELECT
  SUM(appointment_date = CURDATE() AND status IN ('Pending','Confirmed')) AS today_open,
  SUM(appointment_date = CURDATE() AND status = 'Completed') AS today_done,
  SUM(appointment_date > CURDATE() AND status IN ('Pending','Confirmed')) AS upcoming,
  SUM(status = 'Pending') AS pending FROM appointments WHERE doctor_id = ?");
$st->execute([$did]);
$s = $st->fetch();

$st = $pdo->prepare("SELECT a.*, u.name AS patient_name, u.contact, u.date_of_birth FROM appointments a
   JOIN users u ON u.user_id = a.user_id
   WHERE a.doctor_id = ? AND a.appointment_date = CURDATE() AND a.status <> 'Cancelled'
   ORDER BY a.slot_number");
$st->execute([$did]);
$queue = $st->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<h1>Good day, <?= e($_SESSION['doctor_name']) ?></h1>
<p class="lead"><?= e(date('l, F j, Y')) ?></p>
<div class="grid g4" style="margin-bottom:20px">
  <div class="stat"><b><?= (int) $s['today_open'] ?></b><span>Still to see today</span></div>
  <div class="stat"><b><?= (int) $s['today_done'] ?></b><span>Seen today</span></div>
  <div class="stat"><b><?= (int) $s['upcoming'] ?></b><span>Upcoming (later dates)</span></div>
  <div class="stat"><b><?= (int) $s['pending'] ?></b><span>Waiting for confirmation</span></div>
</div>
<h2>Today's queue</h2>
<div class="table-wrap"><table>
  <thead><tr><th>Queue</th><th>Time</th><th>Patient</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($queue as $r): $open = in_array($r['status'], ['Pending', 'Confirmed'], true); ?>
    <tr><td><b><?= str_pad((string) $r['slot_number'], 3, '0', STR_PAD_LEFT) ?></b></td>
      <td><?= e(fmt_time($r['appointment_time'])) ?></td>
      <td><?= e($r['patient_name']) ?><br><span class="hint"><?= e($r['contact']) ?></span></td>
      <td><?= e($r['reason'] ?: '-') ?></td><td><?= status_badge($r['status']) ?></td>
      <td><?php if ($open) {
          if ($r['status'] === 'Pending') echo action_btn('/doctor/appointments.php', (int) $r['appointment_id'], 'confirm', 'Confirm', 'btn-ok');
          echo action_btn('/doctor/appointments.php', (int) $r['appointment_id'], 'complete', 'Complete visit', '');
          echo action_btn('/doctor/appointments.php', (int) $r['appointment_id'], 'noshow', 'No show', 'btn-outline', 'Mark as no show?');
      } else echo '<span class="hint">-</span>'; ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$queue): ?><tr><td colspan="6" class="empty">No patients booked for today.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
