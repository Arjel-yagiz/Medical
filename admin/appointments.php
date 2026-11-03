<?php
$pageTitle = 'Manage Appointments'; $area = 'admin';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action'); $id = (int) post('appointment_id');
    if ($action === 'confirm')   flash(...(set_status($pdo, $id, 'Confirmed') ? ['success', 'Appointment confirmed and patient notified.'] : ['error', 'Cannot confirm this appointment.']));
    elseif ($action === 'complete') flash(...(set_status($pdo, $id, 'Completed') ? ['success', 'Marked as completed.'] : ['error', 'Cannot complete this appointment.']));
    elseif ($action === 'noshow')   flash(...(set_status($pdo, $id, 'No Show') ? ['success', 'Marked as No Show.'] : ['error', 'Cannot update this appointment.']));
    elseif ($action === 'cancel')   flash(...(cancel_appointment($pdo, $id, 'admin') ? ['success', 'Appointment cancelled and patient notified.'] : ['error', 'Cannot cancel this appointment.']));
    elseif ($action === 'reschedule') {
        [$ok, $msg] = reschedule_appointment($pdo, $id, (int) post('schedule_id'));
        flash($ok ? 'success' : 'error', $msg);
    }
    redirect('/admin/appointments.php');
}

// Reschedule form for one appointment
$resId = (int) ($_GET['reschedule'] ?? 0); $res = null; $options = [];
if ($resId) {
    $st = $pdo->prepare('SELECT a.*, u.name AS patient_name, d.doctor_name FROM appointments a JOIN users u ON u.user_id = a.user_id JOIN doctors d ON d.doctor_id = a.doctor_id WHERE a.appointment_id = ?');
    $st->execute([$resId]); $res = $st->fetch();
    if ($res && in_array($res['status'], ['Pending', 'Confirmed'], true)) {
        $st = $pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM appointments a WHERE a.schedule_id = s.schedule_id AND a.status <> 'Cancelled') AS used
            FROM schedules s WHERE s.doctor_id = ? AND s.appointment_date >= CURDATE() AND s.schedule_id <> ? ORDER BY s.appointment_date LIMIT 90");
        $st->execute([$res['doctor_id'], $res['schedule_id']]);
        $options = $st->fetchAll();
    } else { $res = null; }
}

$fStatus = $_GET['status'] ?? ''; $fDate = $_GET['date'] ?? ''; $fDoc = (int) ($_GET['doctor'] ?? 0); $q = trim($_GET['q'] ?? '');
$sql = "SELECT a.*, u.name AS patient_name, u.patient_id, d.doctor_name, dp.department_name FROM appointments a
   JOIN users u ON u.user_id = a.user_id JOIN doctors d ON d.doctor_id = a.doctor_id JOIN departments dp ON dp.department_id = d.department_id WHERE 1=1";
$args = [];
if (in_array($fStatus, ['Pending', 'Confirmed', 'Completed', 'Cancelled', 'No Show'], true)) { $sql .= ' AND a.status = ?'; $args[] = $fStatus; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fDate)) { $sql .= ' AND a.appointment_date = ?'; $args[] = $fDate; }
if ($fDoc) { $sql .= ' AND a.doctor_id = ?'; $args[] = $fDoc; }
if ($q !== '') { $sql .= ' AND (u.name LIKE ? OR a.appointment_no LIKE ? OR u.patient_id LIKE ?)'; $like = '%' . $q . '%'; array_push($args, $like, $like, $like); }
$st = $pdo->prepare($sql . ' ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 300');
$st->execute($args);
$rows = $st->fetchAll();
$doctors = $pdo->query('SELECT doctor_id, doctor_name FROM doctors ORDER BY doctor_name')->fetchAll();
require __DIR__ . '/../includes/header.php';

function act_form(int $id, string $action, string $label, string $cls, string $confirm = ''): string {
    return '<form method="post" class="inline-form"' . ($confirm ? ' onsubmit="return confirm(\'' . e($confirm) . '\')"' : '') . '>' . csrf_field() .
        '<input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="appointment_id" value="' . $id . '">' .
        '<button class="btn btn-sm ' . $cls . '">' . $label . '</button></form> ';
}
?>
<h1>Manage Appointments</h1>

<?php if ($res): ?>
<div class="card" style="max-width:720px">
  <h2>Reschedule <?= e($res['appointment_no']) ?></h2>
  <p>Patient <b><?= e($res['patient_name']) ?></b> &middot; <?= e($res['doctor_name']) ?> &middot; currently <?= e(fmt_date($res['appointment_date'])) ?> <?= e(fmt_time($res['appointment_time'])) ?></p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reschedule"><input type="hidden" name="appointment_id" value="<?= (int) $res['appointment_id'] ?>">
    <label>New date (same doctor)</label>
    <select name="schedule_id" required><option value="">-- Select date --</option>
    <?php foreach ($options as $o): $av = min(MAX_SLOTS_PER_DAY, (int) $o['max_slots']) - (int) $o['used']; ?>
      <option value="<?= (int) $o['schedule_id'] ?>" <?= $av <= 0 ? 'disabled' : '' ?>><?= e(date('D, M j, Y', strtotime($o['appointment_date']))) ?> &mdash; <?= $av <= 0 ? 'FULLY BOOKED' : $av . ' slots available' ?></option>
    <?php endforeach; ?></select>
    <p><button class="btn">Reschedule</button> <a class="btn btn-outline" href="appointments.php">Back</a></p>
  </form>
</div>
<?php endif; ?>

<form method="get" class="toolbar">
  <div><label>Search</label><input name="q" placeholder="Patient / Appt No. / ID" value="<?= e($q) ?>"></div>
  <div><label>Status</label><select name="status"><option value="">All</option>
    <?php foreach (['Pending', 'Confirmed', 'Completed', 'Cancelled', 'No Show'] as $s): ?><option <?= $fStatus === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
  <div><label>Date</label><input type="date" name="date" value="<?= e($fDate) ?>"></div>
  <div><label>Doctor</label><select name="doctor"><option value="0">All</option>
    <?php foreach ($doctors as $d): ?><option value="<?= (int) $d['doctor_id'] ?>" <?= $fDoc === (int) $d['doctor_id'] ? 'selected' : '' ?>><?= e($d['doctor_name']) ?></option><?php endforeach; ?></select></div>
  <div><button class="btn">Filter</button> <a class="btn btn-outline" href="appointments.php">Reset</a></div>
</form>

<div class="table-wrap"><table>
  <thead><tr><th>Appt No.</th><th>Patient</th><th>Department</th><th>Doctor</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $open = in_array($r['status'], ['Pending', 'Confirmed'], true); ?>
    <tr><td><b><?= e($r['appointment_no']) ?></b></td>
      <td><?= e($r['patient_name']) ?><br><span class="hint"><?= e($r['patient_id']) ?></span></td>
      <td><?= e($r['department_name']) ?></td><td><?= e($r['doctor_name']) ?></td>
      <td><?= e(fmt_date($r['appointment_date'])) ?></td><td><?= e(fmt_time($r['appointment_time'])) ?></td>
      <td><?= status_badge($r['status']) ?></td>
      <td>
        <?php if ($r['status'] === 'Pending') echo act_form((int) $r['appointment_id'], 'confirm', 'Confirm', 'btn-ok'); ?>
        <?php if ($open): ?>
          <?= act_form((int) $r['appointment_id'], 'complete', 'Complete', '') ?>
          <a class="btn btn-sm btn-outline" href="?reschedule=<?= (int) $r['appointment_id'] ?>">Reschedule</a>
          <?= act_form((int) $r['appointment_id'], 'noshow', 'No Show', 'btn-outline', 'Mark as No Show?') ?>
          <?= act_form((int) $r['appointment_id'], 'cancel', 'Cancel', 'btn-danger', 'Cancel this appointment?') ?>
        <?php else: ?><span class="hint">&mdash;</span><?php endif; ?>
      </td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="empty">No appointments found.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
