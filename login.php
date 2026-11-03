<?php
$pageTitle = 'Login'; $area = 'public';
require_once __DIR__ . '/includes/functions.php';
if (is_patient()) redirect('/patient/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = post('username'); $p = (string) ($_POST['password'] ?? '');
    if ($u === '' || $p === '') {
        $error = 'Username and password are required.';
    } else {
        $st = $pdo->prepare('SELECT user_id, name, password FROM users WHERE username = ?');
        $st->execute([$u]);
        $row = $st->fetch();
        if ($row && password_verify($p, $row['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $row['user_id'];
            $_SESSION['user_name'] = $row['name'];
            redirect('/patient/dashboard.php');
        }
        $error = 'Invalid username or password.';
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="auth card">
  <h1>Patient Login</h1>
  <p class="lead">Sign in to book and manage your appointments.</p>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Username</label><input name="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
    <label>Password</label><input type="password" name="password" required>
    <p><button class="btn" type="submit">Login</button> <a href="<?= BASE_URL ?>/register.php">Create an account</a></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
