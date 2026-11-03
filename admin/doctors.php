<?php
$pageTitle = 'Manage Doctors'; $area = 'admin';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action'); $id = (int) post('doctor_id');
    try {
        if ($action === 'add' || $action === 'edit') {
            $dept = (int) post('department_id'); $name = post('doctor_name'); $spec = post('specialization');
            $lic = post('license_no'); $exp = post('experience_years'); $room = post('clinic_room'); $fee = post('consultation_fee');
            $days = array_values(array_intersect($DAYS, (array) ($_POST['days'] ?? [])));
            $status = post('status') === 'Inactive' ? 'Inactive' : 'Available';
            $uname = post('username'); $pw = (string) ($_POST['password'] ?? '');
            $uq = $pdo->prepare('SELECT COUNT(*) FROM doctors WHERE username = ? AND doctor_id <> ?'); $uq->execute([$uname, $id]); $taken = $uq->fetchColumn() > 0;
            $chk = $pdo->prepare('SELECT COUNT(*) FROM departments WHERE department_id = ?'); $chk->execute([$dept]);
            if ($name === '' || $spec === '' || !$days || !$chk->fetchColumn()) {
                flash('error', 'Name, specialization, department and at least one available day are required.');
            } elseif (($exp !== '' && !ctype_digit($exp)) || ($fee !== '' && !is_numeric($fee))) {
                flash('error', 'Experience and fee must be numbers.');
            } elseif (!preg_match('/^[A-Za-z0-9_]{4,30}$/', $uname)) {
                flash('error', 'Login username must be 4-30 letters, numbers or underscore.');
            } elseif (($action === 'add' || $pw !== '') && strlen($pw) < 8) {
                flash('error', 'Login password must be at least 8 characters.');
            } elseif ($taken) {
                flash('error', 'That login username is already used by another doctor.');
            } else {
                $vals = [$dept, $name, $spec, $lic ?: null, $exp !== '' ? (int) $exp : null, $room ?: null, $fee !== '' ? $fee : null, implode(',', $days), $status, $uname];
                if ($action === 'add') {
                    $pdo->prepare('INSERT INTO doctors (department_id, doctor_name, specialization, license_no, experience_years, clinic_room, consultation_fee, available_days, status, username, password) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute(array_merge($vals, [password_hash($pw, PASSWORD_DEFAULT)]));
                    flash('success', 'Doctor added. Now add schedules for this doctor.');
                } else {
                    $vals[] = $id;
                    $pdo->prepare('UPDATE doctors SET department_id=?, doctor_name=?, specialization=?, license_no=?, experience_years=?, clinic_room=?, consultation_fee=?, available_days=?, status=?, username=? WHERE doctor_id=?')->execute($vals);
                    if ($pw !== '') $pdo->prepare('UPDATE doctors SET password = ? WHERE doctor_id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
                    flash('success', 'Doctor updated.');
                }
            }
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM doctors WHERE doctor_id = ?')->execute([$id]);
            flash('success', 'Doctor deleted.');
        }
    } catch (PDOException $ex) {
        flash('error', $ex->getCode() === '23000'
            ? 'Cannot delete: this doctor already has schedules/appointments. Set the doctor to Inactive instead.'
            : 'Database error.');
    }
    redirect('/admin/doctors.php');
}

$depts = $pdo->query('SELECT * FROM departments ORDER BY department_name')->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0); $edit = null;
if ($editId) { $st = $pdo->prepare('SELECT * FROM doctors WHERE doctor_id = ?'); $st->execute([$editId]); $edit = $st->fetch(); }
$editDays = $edit ? explode(',', $edit['available_days']) : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
$rows = $pdo->query('SELECT d.*, dp.department_name FROM doctors d JOIN departments dp ON dp.department_id = d.department_id ORDER BY dp.department_name, d.doctor_name')->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<h1>Manage Doctors</h1>
<div class="card">
  <h2><?= $edit ? 'Edit doctor' : 'Add doctor' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $edit ? 'edit' : 'add' ?>">
    <input type="hidden" name="doctor_id" value="<?= (int) ($edit['doctor_id'] ?? 0) ?>">
    <div class="form-row">
      <div><label>Doctor name *</label><input name="doctor_name" required maxlength="100" value="<?= e($edit['doctor_name'] ?? '') ?>"></div>
      <div><label>Department *</label><select name="department_id" required>
        <option value="">-- Select --</option>
        <?php foreach ($depts as $d): ?><option value="<?= (int) $d['department_id'] ?>" <?= ($edit['department_id'] ?? 0) == $d['department_id'] ? 'selected' : '' ?>><?= e($d['department_name']) ?></option><?php endforeach; ?>
      </select></div>
      <div><label>Specialization *</label><input name="specialization" required maxlength="100" value="<?= e($edit['specialization'] ?? '') ?>"></div>
      <div><label>License no.</label><input name="license_no" maxlength="30" value="<?= e($edit['license_no'] ?? '') ?>"></div>
      <div><label>Experience (years)</label><input name="experience_years" type="number" min="0" max="80" value="<?= e($edit['experience_years'] ?? '') ?>"></div>
      <div><label>Clinic room</label><input name="clinic_room" maxlength="30" value="<?= e($edit['clinic_room'] ?? '') ?>"></div>
      <div><label>Consultation fee (&#8369;)</label><input name="consultation_fee" type="number" step="0.01" min="0" value="<?= e($edit['consultation_fee'] ?? '') ?>"></div>
      <div><label>Status</label><select name="status"><?php foreach (['Available', 'Inactive'] as $s): ?><option <?= ($edit['status'] ?? 'Available') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
      <div><label>Login username *</label><input name="username" required maxlength="30" autocomplete="off" value="<?= e($edit['username'] ?? '') ?>"></div>
      <div><label>Login password <?= $edit ? '<span class="hint">(blank = keep current)</span>' : '*' ?></label><input type="password" name="password" autocomplete="new-password" <?= $edit ? '' : 'required' ?>></div>
    </div>
    <label>Available days *</label>
    <div class="checks"><?php foreach ($DAYS as $d): ?><label><input type="checkbox" name="days[]" value="<?= $d ?>" <?= in_array($d, $editDays, true) ? 'checked' : '' ?>><?= $d ?></label><?php endforeach; ?></div>
    <p><button class="btn" type="submit"><?= $edit ? 'Update doctor' : 'Add doctor' ?></button>
       <?php if ($edit): ?><a class="btn btn-outline" href="doctors.php">Cancel</a><?php endif; ?></p>
  </form>
</div>
<div class="table-wrap"><table>
  <thead><tr><th>Doctor</th><th>Department</th><th>Specialization</th><th>Available days</th><th>Room</th><th>Fee</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><b><?= e($r['doctor_name']) ?></b><br><span class="hint"><?= e($r['license_no']) ?> &middot; login: <?= e($r['username'] ?? 'none') ?></span></td><td><?= e($r['department_name']) ?></td><td><?= e($r['specialization']) ?></td>
      <td><?= e(str_replace(',', ', ', $r['available_days'])) ?></td><td><?= e($r['clinic_room']) ?></td>
      <td><?= $r['consultation_fee'] !== null ? '&#8369;' . number_format((float) $r['consultation_fee'], 2) : '-' ?></td>
      <td><?= $r['status'] === 'Available' ? '<span class="badge badge-ok">Available</span>' : '<span class="badge badge-muted">Inactive</span>' ?></td>
      <td><a class="btn btn-sm btn-outline" href="?edit=<?= (int) $r['doctor_id'] ?>">Edit</a>
        <form method="post" class="inline-form" onsubmit="return confirm('Delete this doctor?')"><?= csrf_field() ?>
          <input type="hidden" name="action" value="delete"><input type="hidden" name="doctor_id" value="<?= (int) $r['doctor_id'] ?>">
          <button class="btn btn-sm btn-danger">Delete</button></form></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="empty">No doctors yet.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
