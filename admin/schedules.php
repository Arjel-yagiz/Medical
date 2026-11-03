<?php
$pageTitle = 'Manage Schedules'; $area = 'admin';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

function valid_time(string $t): bool { return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    try {
        if ($action === 'add' || $action === 'bulk') {
            $doc = (int) post('doctor_id'); $start = post('start_time'); $end = post('end_time'); $max = (int) post('max_slots');
            $st = $pdo->prepare('SELECT available_days FROM doctors WHERE doctor_id = ?'); $st->execute([$doc]); $days = $st->fetchColumn();
            if ($days === false) flash('error', 'Select a doctor.');
            elseif (!valid_time($start) || !valid_time($end) || $start >= $end) flash('error', 'Enter a valid start and end time (end after start).');
            elseif ($max < 1 || $max > MAX_SLOTS_PER_DAY) flash('error', 'Max slots must be between 1 and ' . MAX_SLOTS_PER_DAY . '.');
            elseif ($action === 'add') {
                $date = post('appointment_date');
                if (!$date || !strtotime($date) || $date < date('Y-m-d')) flash('error', 'Choose a valid date (today or later).');
                else {
                    $pdo->prepare('INSERT INTO schedules (doctor_id, appointment_date, start_time, end_time, max_slots) VALUES (?,?,?,?,?)')->execute([$doc, $date, $start, $end, $max]);
                    flash('success', 'Schedule added.');
                }
            } else {
                $from = post('from_date'); $to = post('to_date');
                if (!$from || !$to || !strtotime($from) || !strtotime($to) || $to < $from || $from < date('Y-m-d')) flash('error', 'Enter a valid date range (from today onward).');
                elseif ((strtotime($to) - strtotime($from)) / 86400 > 92) flash('error', 'Range is limited to 92 days.');
                else {
                    $allowed = explode(',', $days); $made = 0;
                    $ins = $pdo->prepare('INSERT IGNORE INTO schedules (doctor_id, appointment_date, start_time, end_time, max_slots) VALUES (?,?,?,?,?)');
                    for ($t = strtotime($from); $t <= strtotime($to); $t += 86400) {
                        if (in_array(date('D', $t), $allowed, true)) { $ins->execute([$doc, date('Y-m-d', $t), $start, $end, $max]); $made += $ins->rowCount(); }
                    }
                    flash('success', "$made schedule(s) created on the doctor's available days.");
                }
            }
        } elseif ($action === 'edit') {
            $id = (int) post('schedule_id'); $start = post('start_time'); $end = post('end_time'); $max = (int) post('max_slots');
            $used = used_slots($pdo, $id);
            if (!valid_time($start) || !valid_time($end) || $start >= $end) flash('error', 'Invalid time range.');
            elseif ($max < 1 || $max > MAX_SLOTS_PER_DAY) flash('error', 'Max slots must be between 1 and ' . MAX_SLOTS_PER_DAY . '.');
            elseif ($max < $used) flash('error', "Max slots cannot be lower than the $used slots already used.");
            else {
                $pdo->prepare('UPDATE schedules SET start_time = ?, end_time = ?, max_slots = ? WHERE schedule_id = ?')->execute([$start, $end, $max, $id]);
                flash('success', 'Schedule updated. (Existing appointment times are kept.)');
            }
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM schedules WHERE schedule_id = ?')->execute([(int) post('schedule_id')]);
            flash('success', 'Schedule deleted.');
        }
    } catch (PDOException $ex) {
        flash('error', $ex->getCode() === '23000' ? 'Not saved: that doctor already has a schedule on this date, or the schedule has appointments.' : 'Database error.');
    }
    redirect('/admin/schedules.php');
}

$doctors = $pdo->query("SELECT doctor_id, doctor_name, available_days FROM doctors WHERE status = 'Available' ORDER BY doctor_name")->fetchAll();
$fDoc = (int) ($_GET['doctor'] ?? 0); $fFrom = $_GET['from'] ?? date('Y-m-d');
$editId = (int) ($_GET['edit'] ?? 0); $edit = null;
if ($editId) { $st = $pdo->prepare('SELECT * FROM schedules WHERE schedule_id = ?'); $st->execute([$editId]); $edit = $st->fetch(); }

$sql = "SELECT s.*, d.doctor_name,
   (SELECT COUNT(*) FROM appointments a WHERE a.schedule_id = s.schedule_id AND a.status <> 'Cancelled') AS used
   FROM schedules s JOIN doctors d ON d.doctor_id = s.doctor_id WHERE s.appointment_date >= ?";
$args = [preg_match('/^\d{4}-\d{2}-\d{2}$/', $fFrom) ? $fFrom : date('Y-m-d')];
if ($fDoc) { $sql .= ' AND s.doctor_id = ?'; $args[] = $fDoc; }
$st = $pdo->prepare($sql . ' ORDER BY s.appointment_date, d.doctor_name LIMIT 200');
$st->execute($args);
$rows = $st->fetchAll();
require __DIR__ . '/../includes/header.php';

function doctor_options(array $doctors, int $sel = 0): string {
    $h = '<option value="">-- Select doctor --</option>';
    foreach ($doctors as $d) $h .= '<option value="' . (int) $d['doctor_id'] . '"' . ($sel === (int) $d['doctor_id'] ? ' selected' : '') . '>' . e($d['doctor_name']) . ' (' . e(str_replace(',', ', ', $d['available_days'])) . ')</option>';
    return $h;
}
?>
<h1>Manage Schedules</h1>
<p class="lead">Each doctor/date schedule has a maximum of <?= MAX_SLOTS_PER_DAY ?> slots. Lower the max (e.g. to 3) to test the "Fully Booked" behaviour quickly.</p>

<?php if ($edit): ?>
<div class="card" style="max-width:700px">
  <h2>Edit schedule &mdash; <?= e(fmt_date($edit['appointment_date'])) ?></h2>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="schedule_id" value="<?= (int) $edit['schedule_id'] ?>">
    <div class="form-row">
      <div><label>Start time</label><input type="time" name="start_time" required value="<?= e(substr($edit['start_time'], 0, 5)) ?>"></div>
      <div><label>End time</label><input type="time" name="end_time" required value="<?= e(substr($edit['end_time'], 0, 5)) ?>"></div>
      <div><label>Max slots (1-<?= MAX_SLOTS_PER_DAY ?>)</label><input type="number" name="max_slots" min="1" max="<?= MAX_SLOTS_PER_DAY ?>" required value="<?= (int) $edit['max_slots'] ?>"></div>
    </div>
    <p><button class="btn">Update</button> <a class="btn btn-outline" href="schedules.php">Cancel</a></p>
  </form>
</div>
<?php endif; ?>

<div class="grid g2">
  <div class="card">
    <h2>Add available date</h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
      <label>Doctor</label><select name="doctor_id" required><?= doctor_options($doctors) ?></select>
      <label>Date</label><input type="date" name="appointment_date" required min="<?= date('Y-m-d') ?>">
      <div class="form-row">
        <div><label>Start</label><input type="time" name="start_time" value="08:00" required></div>
        <div><label>End</label><input type="time" name="end_time" value="17:00" required></div>
        <div><label>Max slots</label><input type="number" name="max_slots" min="1" max="<?= MAX_SLOTS_PER_DAY ?>" value="<?= MAX_SLOTS_PER_DAY ?>" required></div>
      </div>
      <p><button class="btn">Add schedule</button></p>
    </form>
  </div>
  <div class="card">
    <h2>Set doctor's schedule (date range)</h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="bulk">
      <label>Doctor</label><select name="doctor_id" required><?= doctor_options($doctors) ?></select>
      <div class="form-row">
        <div><label>From</label><input type="date" name="from_date" required min="<?= date('Y-m-d') ?>"></div>
        <div><label>To</label><input type="date" name="to_date" required min="<?= date('Y-m-d') ?>"></div>
        <div><label>Start</label><input type="time" name="start_time" value="08:00" required></div>
        <div><label>End</label><input type="time" name="end_time" value="17:00" required></div>
        <div><label>Max slots</label><input type="number" name="max_slots" min="1" max="<?= MAX_SLOTS_PER_DAY ?>" value="<?= MAX_SLOTS_PER_DAY ?>" required></div>
      </div>
      <p class="hint">Creates schedules only on the doctor's available days; existing dates are skipped.</p>
      <p><button class="btn">Generate schedules</button></p>
    </form>
  </div>
</div>

<h2>View schedules</h2>
<form method="get" class="toolbar">
  <div><label>Doctor</label><select name="doctor"><option value="0">All doctors</option>
    <?php foreach ($doctors as $d): ?><option value="<?= (int) $d['doctor_id'] ?>" <?= $fDoc === (int) $d['doctor_id'] ? 'selected' : '' ?>><?= e($d['doctor_name']) ?></option><?php endforeach; ?></select></div>
  <div><label>From date</label><input type="date" name="from" value="<?= e($fFrom) ?>"></div>
  <div><button class="btn">Filter</button></div>
</form>
<div class="table-wrap"><table>
  <thead><tr><th>Date</th><th>Doctor</th><th>Hours</th><th>Total</th><th>Used</th><th>Available</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r):
      $total = min(MAX_SLOTS_PER_DAY, (int) $r['max_slots']); $used = (int) $r['used']; $av = max(0, $total - $used);
      $stt = availability_status($av, $total); $cls = $stt === 'Available' ? 'ok' : ($stt === 'Almost Full' ? 'warn' : 'bad'); ?>
    <tr><td><?= e(date('D, M j, Y', strtotime($r['appointment_date']))) ?></td><td><?= e($r['doctor_name']) ?></td>
      <td><?= e(fmt_time($r['start_time'])) ?> - <?= e(fmt_time($r['end_time'])) ?></td>
      <td><?= $total ?></td><td><?= $used ?></td><td><?= $av ?></td>
      <td><span class="badge badge-<?= $cls ?>"><?= $stt === 'Fully Booked' ? 'FULLY BOOKED' : e($stt) ?></span></td>
      <td><a class="btn btn-sm btn-outline" href="?edit=<?= (int) $r['schedule_id'] ?>">Edit</a>
        <form method="post" class="inline-form" onsubmit="return confirm('Delete this schedule?')"><?= csrf_field() ?>
          <input type="hidden" name="action" value="delete"><input type="hidden" name="schedule_id" value="<?= (int) $r['schedule_id'] ?>">
          <button class="btn btn-sm btn-danger" <?= $used ? 'disabled title="Has appointments"' : '' ?>>Delete</button></form></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="empty">No schedules found.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>