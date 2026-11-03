<?php
$pageTitle = 'Forgot password'; $area = 'public';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
if (is_patient()) redirect('/patient/dashboard.php');

const OTP_MINUTES = 10;
$error = '';
$prefill = strtolower(trim((string) ($_GET['email'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(post('email'));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (isset($_SESSION['otp_last']) && time() - $_SESSION['otp_last'] < 60) {
        $error = 'Please wait a minute before asking for another code.';
    } else {
        $_SESSION['otp_last'] = time();
        $st = $pdo->prepare('SELECT user_id, name FROM users WHERE email = ?');
        $st->execute([$email]);
        $u = $st->fetch();
        if ($u) {
            $c = $pdo->prepare('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
            $c->execute([$u['user_id']]);
            if ($c->fetchColumn() < 3) {                       // max 3 codes per hour
                $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$u['user_id']]);
                $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $pdo->prepare('INSERT INTO password_resets (user_id, otp_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ' . OTP_MINUTES . ' MINUTE))')
                    ->execute([$u['user_id'], password_hash($otp, PASSWORD_DEFAULT)]);
                [$html, $text] = email_otp_template($u['name'], $otp, OTP_MINUTES);
                send_mail($email, 'Your Queue&Care password reset code', $html, $text);
                if (mail_dev_mode() && MAIL_DEV_SHOW_OTP) flash('info', "DEV MODE (no SMTP set up): your code is $otp");
            }
        }
        // same answer whether or not the email exists (prevents guessing who is registered)
        $_SESSION['reset_email'] = $email;
        flash('success', 'If that email is registered, we sent a 6-digit code. It expires in ' . OTP_MINUTES . ' minutes. Check your spam folder too.');
        redirect('/reset_password.php');
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="auth card">
  <h1>Forgot your password?</h1>
  <p class="lead">Enter the email you registered with. We will send you a 6-digit code.</p>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Email address</label>
    <input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? $prefill) ?>" placeholder="you@example.com">
    <p><button class="btn" type="submit">Send code</button> <a href="<?= BASE_URL ?>/login.php">Back to login</a></p>
  </form>
  <p class="hint">This is for patient accounts. Doctors and staff should ask the clinic admin to reset their password.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
