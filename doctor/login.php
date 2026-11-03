<?php
$pageTitle = 'Doctor Login'; $area = 'doctor';
require_once __DIR__ . '/../includes/functions.php';
if (is_doctor()) redirect('/doctor/index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = post('username'); $p = (string) ($_POST['password'] ?? '');
    $st = $pdo->prepare('SELECT doctor_id, doctor_name, password, status FROM doctors WHERE username = ?');
    $st->execute([$u]);
    $row = $st->fetch();
    if ($u !== '' && $row && $row['password'] && password_verify($p, $row['password'])) {
        if ($row['status'] !== 'Available') {
            $error = 'This doctor account is inactive. Please contact the clinic admin.';
        } else {
            session_regenerate_id(true);
            $_SESSION['doctor_id'] = (int) $row['doctor_id'];
            $_SESSION['doctor_name'] = $row['doctor_name'];
            redirect('/doctor/index.php');
        }
    } else {
        $error = 'Invalid doctor username or password.';
    }
}
require __DIR__ . '/../includes/header.php';
?>
<div class="auth card">
  <h1>Doctor Login</h1>
  <p class="lead">See your daily queue and update patient visits.</p>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Username</label><input name="username" required autofocus>
    <label>Password</label><input type="password" name="password" required>
    <p><button class="btn" type="submit">Login</button></p>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
