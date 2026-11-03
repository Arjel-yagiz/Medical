<?php
$pageTitle = 'Email test'; $area = 'admin';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_admin();
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $result = send_mail(post('to'), 'Queue&Care test email', '<p>It works! Your SMTP settings are correct.</p>');
}
require __DIR__ . '/../includes/header.php';
?>
<h1>Email connection test</h1>
<p class="lead">Mode: <b><?= mail_dev_mode() ? 'DEV (no SMTP set - emails are only logged)' : 'SMTP via ' . e(MAIL_HOST) . ':' . (int) MAIL_PORT ?></b></p>
<div class="card" style="max-width:520px">
  <?php if ($result): ?><div class="alert alert-<?= $result[0] ? 'success' : 'error' ?>"><?= e($result[1]) ?></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?>
    <label>Send a test email to</label><input type="email" name="to" required placeholder="you@example.com">
    <p><button class="btn">Send test</button></p>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
