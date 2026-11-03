<?php
$pageTitle = 'Profile'; $area = 'doctor';
require_once __DIR__ . '/../includes/functions.php';
require_doctor();
$did = (int) $_SESSION['doctor_id'];
$st = $pdo->prepare('SELECT d.*, dp.department_name FROM doctors d JOIN departments dp ON dp.department_id = d.department_id WHERE d.doctor_id = ?');
$st->execute([$did]);
$d = $st->fetch();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $cur = (string) ($_POST['current_password'] ?? ''); $new = (string) ($_POST['new_password'] ?? ''); $new2 = (string) ($_POST['new_password2'] ?? '');
    if (!password_verify($cur, (string) $d['password'])) $errors[] = 'Current password is incorrect.';
    if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
    if ($new !== $new2) $errors[] = 'New passwords do not match.';
    if (!$errors) {
        $pdo->prepare('UPDATE doctors SET password = ? WHERE doctor_id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $did]);
        flash('success', 'Password changed.');
        redirect('/doctor/profile.php');
    }
}
require __DIR__ . '/../includes/header.php';
?>
<h1>My Profile</h1>
<div class="grid g2">
  <div class="card summary"><dl>
    <dt>Name</dt><dd><?= e($d['doctor_name']) ?></dd>
    <dt>Department</dt><dd><?= e($d['department_name']) ?></dd>
    <dt>Specialization</dt><dd><?= e($d['specialization']) ?></dd>
    <dt>License no.</dt><dd><?= e($d['license_no'] ?: '-') ?></dd>
    <dt>Clinic room</dt><dd><?= e($d['clinic_room'] ?: '-') ?></dd>
    <dt>Clinic days</dt><dd><?= e(str_replace(',', ', ', $d['available_days'])) ?></dd>
    <dt>Username</dt><dd><?= e($d['username']) ?></dd>
  </dl><p class="hint">To change these details, ask the admin.</p></div>
  <div class="card">
    <h2>Change password</h2>
    <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post"><?= csrf_field() ?>
      <label>Current password</label><input type="password" name="current_password" required autocomplete="current-password">
      <label>New password</label><input type="password" name="new_password" required autocomplete="new-password">
      <label>Confirm new password</label><input type="password" name="new_password2" required autocomplete="new-password">
      <p><button class="btn">Save password</button></p>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
