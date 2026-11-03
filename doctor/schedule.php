<?php
$pageTitle = 'My Schedule'; $area = 'doctor';
require_once __DIR__ . '/../includes/functions.php';
require_doctor();
$st = $pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM appointments a WHERE a.schedule_id = s.schedule_id AND a.status <> 'Cancelled') AS used
   FROM schedules s WHERE s.doctor_id = ? AND s.appointment_date >= CURDATE() ORDER BY s.appointment_date LIMIT 60");
$st->execute([(int) $_SESSION['doctor_id']]);
$rows = $st->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<h1>My Schedule</h1>
<p class="lead">Your upcoming clinic days. Ask the admin to add or change dates.</p>
<div class="table-wrap"><table>
  <thead><tr><th>Date</th><th>Hours</th><th>Total</th><th>Booked</th><th>Open</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r):
      $total = min(MAX_SLOTS_PER_DAY, (int) $r['max_slots']); $used = (int) $r['used']; $av = max(0, $total - $used);
      $stt = availability_status($av, $total); $cls = $stt === 'Available' ? 'ok' : ($stt === 'Almost Full' ? 'warn' : 'bad'); ?>
    <tr><td><?= e(date('D, M j, Y', strtotime($r['appointment_date']))) ?></td>
      <td><?= e(fmt_time($r['start_time'])) ?> - <?= e(fmt_time($r['end_time'])) ?></td>
      <td><?= $total ?></td><td><?= $used ?></td><td><?= $av ?></td>
      <td><span class="badge badge-<?= $cls ?>"><?= $stt === 'Fully Booked' ? 'FULLY BOOKED' : e($stt) ?></span></td>
      <td><a class="btn btn-sm btn-outline" href="appointments.php?date=<?= e($r['appointment_date']) ?>">View patients</a></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="empty">No upcoming schedules yet.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
