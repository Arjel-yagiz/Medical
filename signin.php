<?php
// One "Log in" entry for everyone: pick Patient, Doctor or Staff.
$pageTitle = 'Log in'; $area = 'public';
require_once __DIR__ . '/includes/functions.php';
if (is_patient()) redirect('/patient/dashboard.php');
if (is_doctor())  redirect('/doctor/index.php');
if (is_admin())   redirect('/admin/index.php');
require __DIR__ . '/includes/header.php';
?>
<div class="auth wide">
  <h1>Log in</h1>
  <p class="lead">Choose how you use Queue&amp;Care.</p>
  <div class="grid g3">
    <a class="choice" href="<?= BASE_URL ?>/login.php">
      <span class="ico">🎟️</span><b>Patient</b><small>Book and manage your appointments</small></a>
    <a class="choice" href="<?= BASE_URL ?>/doctor/login.php">
      <span class="ico">🩺</span><b>Doctor</b><small>See your queue and mark visits done</small></a>
    <a class="choice" href="<?= BASE_URL ?>/admin/login.php">
      <span class="ico">🛠️</span><b>Admin</b><small>Manage doctors, schedules and bookings</small></a>
  </div>
  <p class="hint" style="margin-top:18px">New patient? <a href="<?= BASE_URL ?>/register.php">Create an account</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
