<?php
$pageTitle = 'Admin Login'; $area = 'admin';
require_once __DIR__ . '/../includes/functions.php';
if (is_admin()) redirect('/admin/index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = post('username'); $p = (string) ($_POST['password'] ?? '');
    $st = $pdo->prepare('SELECT admin_id, name, password FROM admins WHERE username = ?');
    $st->execute([$u]);
    $row = $st->fetch();
    if ($u !== '' && $row && password_verify($p, $row['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $row['admin_id'];
        $_SESSION['admin_name'] = $row['name'];
        redirect('/admin/index.php');
    }
    $error = 'Invalid admin username or password.';
}
require __DIR__ . '/../includes/header.php';
?>
<div class="auth card">
  <h1>Admin Login</h1>
  <p class="lead">Authorized personnel only.</p>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Username</label><input name="username" required autofocus>
    <label>Password</label><input type="password" name="password" required>
    <p><button class="btn" type="submit">Login</button></p>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
