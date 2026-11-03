<?php
$pageTitle = 'Reset password'; $area = 'public';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
if (is_patient()) redirect('/patient/dashboard.php');
$email = (string) ($_SESSION['reset_email'] ?? '');
if ($email === '') redirect('/forgot_password.php');

const MAX_OTP_TRIES = 5;
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $otp = preg_replace('/\D/', '', post('otp'));
    $pw = (string) ($_POST['password'] ?? ''); $pw2 = (string) ($_POST['password2'] ?? '');
    if (strlen($otp) !== 6) $errors[] = 'Enter the 6-digit code from your email.';
    if (strlen($pw) < 8) $errors[] = 'New password must be at least 8 characters.';
    if ($pw !== $pw2) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $st = $pdo->prepare('SELECT user_id, name FROM users WHERE email = ?');
        $st->execute([$email]);
        $u = $st->fetch();
        $row = false;
        if ($u) {
            $st = $pdo->prepare('SELECT * FROM password_resets WHERE user_id = ? AND used = 0 AND expires_at > NOW() ORDER BY reset_id DESC LIMIT 1');
            $st->execute([$u['user_id']]);
            $row = $st->fetch();
        }
        if (!$row || $row['attempts'] >= MAX_OTP_TRIES) {
            $errors[] = 'Invalid or expired code. Request a new one.';
        } elseif (!password_verify($otp, $row['otp_hash'])) {
            $pdo->prepare('UPDATE password_resets SET attempts = attempts + 1 WHERE reset_id = ?')->execute([$row['reset_id']]);
            $errors[] = 'Invalid or expired code. Request a new one.';
        } else {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $u['user_id']]);
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$u['user_id']]);
            $pdo->commit();
            notify($pdo, (int) $u['user_id'], null, 'Info', 'Password changed', 'Your password was changed using an email code. If this was not you, contact the clinic right away.');
            send_mail($email, 'Your Queue&Care password was changed',
                '<p>Hi ' . e($u['name']) . ',</p><p>Your password was just changed. If this was not you, contact the clinic immediately.</p>');
            unset($_SESSION['reset_email'], $_SESSION['otp_last']);
            session_regenerate_id(true);
            flash('success', 'Password changed. You can log in with your new password.');
            redirect('/login.php');
        }
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="auth card">
  <h1>Enter your code</h1>
  <p class="lead">We sent a 6-digit code to <b><?= e($email) ?></b> (if it is registered). It expires in 10 minutes.</p>
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>6-digit code</label>
    <input name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus
           style="font-size:1.8rem;letter-spacing:.45em;text-align:center;font-weight:700" placeholder="······">
    <label>New password <span class="hint">(min. 8 characters)</span></label>
    <input type="password" name="password" required autocomplete="new-password">
    <label>Confirm new password</label>
    <input type="password" name="password2" required autocomplete="new-password">
    <p><button class="btn" type="submit">Change password</button></p>
  </form>
  <p class="hint">No email? <a href="<?= BASE_URL ?>/forgot_password.php?email=<?= urlencode($email) ?>">Send a new code</a> &middot; <a href="<?= BASE_URL ?>/login.php">Back to login</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
