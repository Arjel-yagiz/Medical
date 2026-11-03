<?php
$pageTitle = 'Profile'; $area = 'patient';
require_once __DIR__ . '/../includes/functions.php';
require_patient();
$uid = (int) $_SESSION['user_id'];
$errors = [];

$st = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
$st->execute([$uid]);
$u = $st->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = post('name'); $email = post('email'); $contact = post('contact'); $address = post('address'); $dob = post('dob'); $gender = post('gender');
    $cur = (string) ($_POST['current_password'] ?? ''); $new = (string) ($_POST['new_password'] ?? ''); $new2 = (string) ($_POST['new_password2'] ?? '');

    if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
    if (!preg_match('/^[0-9+\-\s]{7,20}$/', $contact)) $errors[] = 'Enter a valid contact number.';
    if ($address === '') $errors[] = 'Address is required.';
    $ts = strtotime($dob);
    if (!$dob || !$ts || $ts > time()) $errors[] = 'Enter a valid date of birth.';
    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) $errors[] = 'Select a gender.';

    $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND user_id <> ?');
    $st->execute([$email, $uid]);
    if ($st->fetchColumn() > 0) $errors[] = 'That email is used by another account.';

    $newHash = null;
    if ($new !== '' || $new2 !== '' || $cur !== '') {
        if (!password_verify($cur, $u['password'])) $errors[] = 'Current password is incorrect.';
        if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
        if ($new !== $new2) $errors[] = 'New passwords do not match.';
        if (!$errors) $newHash = password_hash($new, PASSWORD_DEFAULT);
    }
    if (!$errors) {
        $pdo->prepare('UPDATE users SET name=?, email=?, contact=?, address=?, date_of_birth=?, gender=? WHERE user_id=?')
            ->execute([$name, $email, $contact, $address, $dob, $gender, $uid]);
        if ($newHash) $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?')->execute([$newHash, $uid]);
        $_SESSION['user_name'] = $name;
        flash('success', 'Profile updated.');
        redirect('/patient/profile.php');
    }
    $u = array_merge($u, ['name' => $name, 'email' => $email, 'contact' => $contact, 'address' => $address, 'date_of_birth' => $dob, 'gender' => $gender]);
}
require __DIR__ . '/../includes/header.php';
?>
<h1>My Profile</h1>
<p class="lead">Patient ID: <b><?= e($u['patient_id']) ?></b> &middot; Username: <b><?= e($u['username']) ?></b></p>
<div class="card" style="max-width:760px">
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="form-row">
      <div><label>Full Name</label><input name="name" required value="<?= e($u['name']) ?>"></div>
      <div><label>Email</label><input type="email" name="email" required value="<?= e($u['email']) ?>"></div>
      <div><label>Contact Number</label><input name="contact" required value="<?= e($u['contact']) ?>"></div>
      <div><label>Date of Birth</label><input type="date" name="dob" required max="<?= date('Y-m-d') ?>" value="<?= e($u['date_of_birth']) ?>"></div>
      <div><label>Gender</label><select name="gender"><?php foreach (['Male', 'Female', 'Other'] as $g): ?><option <?= $u['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?></select></div>
    </div>
    <label>Address</label><input name="address" required value="<?= e($u['address']) ?>">
    <h3 style="margin-top:22px">Change password <span class="hint">(leave blank to keep current)</span></h3>
    <div class="form-row">
      <div><label>Current password</label><input type="password" name="current_password" autocomplete="current-password"></div>
      <div><label>New password</label><input type="password" name="new_password" autocomplete="new-password"></div>
      <div><label>Confirm new password</label><input type="password" name="new_password2" autocomplete="new-password"></div>
    </div>
    <p><button class="btn" type="submit">Save changes</button></p>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
