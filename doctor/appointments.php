<?php
$pageTitle = 'My Appointments'; $area = 'doctor';
require_once __DIR__ . '/../includes/functions.php';
require_doctor();
$did = (int) $_SESSION['doctor_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) post('appointment_id'); $action = post('action');
    // access control: a doctor can only change their own patients' appointments
    $own = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE appointment_id = ? AND doctor_id = ?');
    $own->execute([$id, $did]);
    $map = ['confirm' => 'Confirmed', 'complete' => 'Completed', 'noshow' => 'No Show'];
    if (!$own->fetchColumn() || !isset($map[$action])) flash('error', 'You cannot change this appointment.');
    elseif (set_status($pdo, $id, $map[$action])) flash('success', 'Marked as ' . $map[$action] . '. The patient was notified.');
    else flash('error', 'This appointment cannot be changed any more.');
    redirect('/doctor/appointments.php');
}

$fStatus = $_GET['status'] ?? ''; $fDate = $_GET['date'] ?? ''; $q = trim($_GET['q'] ?? '');
$sql = "SELECT a.*, u.name AS patient_name, u.contact, u.patient_id FROM appointments a JOIN users u ON u.user_id = a.user_id WHERE a.doctor_id = ?";
$args = [$did];
if (in_array($fStatus, ['Pending', 'Confirmed', 'Completed', 'Cancelled', 'No Show'], true)) { $sql .= ' AND a.status = ?'; $args[] = $fStatus; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fDate)) { $sql .= ' AND a.appointment_date = ?'; $args[] = $fDate; }
if ($q !== '') { $sql .= ' AND (u.name LIKE ? OR a.appointment_no LIKE ?)'; $like = '%' . $q . '%'; array_push($args, $like, $like); }
$st = $pdo->prepare($sql . ' ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 300');
$st->execute($args);
$rows = $st->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<h1>My Appointments</h1>
<form method="get" class="toolbar">
  <div><label>Search</label><input name="q" placeholder="Patient or appointment no." value="<?= e($q) ?>"></div>
  <div><label>Status</label><select name="status"><option value="">All</option>
    <?php foreach (['Pending', 'Confirmed', 'Completed', 'Cancelled', 'No Show'] as $s): ?><option <?= $fStatus === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
  <div><label>Date</label><input type="date" name="date" value="<?= e($fDate) ?>"></div>
  <div><button class="btn">Filter</button> <a class="btn btn-outline" href="appointments.php">Reset</a></div>
</form>
<div class="table-wrap"><table>
  <thead><tr><th>Appt No.</th><th>Patient</th><th>Date</th><th>Time</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $open = in_array($r['status'], ['Pending', 'Confirmed'], true); ?>
    <tr><td><b><?= e($r['appointment_no']) ?></b></td>
      <td><?= e($r['patient_name']) ?><br><span class="hint"><?= e($r['patient_id']) ?> &middot; <?= e($r['contact']) ?></span></td>
      <td><?= e(fmt_date($r['appointment_date'])) ?></td><td><?= e(fmt_time($r['appointment_time'])) ?></td>
      <td><?= e($r['reason'] ?: '-') ?></td><td><?= status_badge($r['status']) ?></td>
      <td><?php if ($open) {
          if ($r['status'] === 'Pending') echo action_btn('/doctor/appointments.php', (int) $r['appointment_id'], 'confirm', 'Confirm', 'btn-ok');
          echo action_btn('/doctor/appointments.php', (int) $r['appointment_id'], 'complete', 'Complete', '');
          echo action_btn('/doctor/appointments.php', (int) $r['appointment_id'], 'noshow', 'No show', 'btn-outline', 'Mark as no show?');
      } else echo '<span class="hint">-</span>'; ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="empty">No appointments match.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
