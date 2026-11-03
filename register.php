<?php
$pageTitle = 'Register'; $area = 'public';
require_once __DIR__ . '/includes/functions.php';
if (is_patient()) redirect('/patient/dashboard.php');

$f = ['name'=>'','email'=>'','contact'=>'','address'=>'','dob'=>'','gender'=>'','username'=>''];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($f as $k => $_) $f[$k] = post($k);
    $pw = (string) ($_POST['password'] ?? ''); $pw2 = (string) ($_POST['password2'] ?? '');

    if ($f['name'] === '' || mb_strlen($f['name']) > 100) $errors[] = 'Full name is required (max 100 characters).';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!preg_match('/^[0-9+\-\s]{7,20}$/', $f['contact'])) $errors[] = 'Enter a valid contact number (digits, +, - only).';
    if ($f['address'] === '') $errors[] = 'Address is required.';
    $dobTs = strtotime($f['dob']);
    if (!$f['dob'] || !$dobTs || $dobTs > time()) $errors[] = 'Enter a valid date of birth.';
    if (!in_array($f['gender'], ['Male', 'Female', 'Other'], true)) $errors[] = 'Select a gender.';
    if (!preg_match('/^[A-Za-z0-9_]{4,30}$/', $f['username'])) $errors[] = 'Username must be 4-30 letters, numbers or underscore.';
    if (strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pw !== $pw2) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
        $st->execute([$f['username'], $f['email']]);
        if ($st->fetchColumn() > 0) $errors[] = 'Username or email is already registered.';
    }
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $ins = $pdo->prepare('INSERT INTO users (patient_id, name, email, contact, address, date_of_birth, gender, username, password) VALUES (?,?,?,?,?,?,?,?,?)');
            $ins->execute(['TMP' . bin2hex(random_bytes(6)), $f['name'], $f['email'], $f['contact'], $f['address'], $f['dob'], $f['gender'], $f['username'], password_hash($pw, PASSWORD_DEFAULT)]);
            $id = (int) $pdo->lastInsertId();
            $pid = 'PT-' . date('Y') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            $pdo->prepare('UPDATE users SET patient_id = ? WHERE user_id = ?')->execute([$pid, $id]);
            notify($pdo, $id, null, 'Info', 'Welcome!', 'Your account was created. Your Patient ID is ' . $pid . '. You can now book an appointment.');
            $pdo->commit();
            flash('success', 'Registration successful! Your Patient ID is ' . $pid . '. Please log in.');
            redirect('/login.php');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Could not create the account. Username or email may already exist.';
        }
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="auth wide card">
  <h1>Create your account</h1>
  <p class="lead">Patient registration</p>
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="form-row">
      <div><label>Full Name *</label><input name="name" required maxlength="100" value="<?= e($f['name']) ?>"></div>
      <div><label>Email *</label><input type="email" name="email" required value="<?= e($f['email']) ?>"></div>
      <div><label>Contact Number *</label><input name="contact" required value="<?= e($f['contact']) ?>"></div>
      <div><label>Date of Birth *</label><input type="date" name="dob" required max="<?= date('Y-m-d') ?>" value="<?= e($f['dob']) ?>"></div>
      <div><label>Gender *</label>
        <select name="gender" required>
          <option value="">-- Select --</option>
          <?php foreach (['Male', 'Female', 'Other'] as $g): ?><option <?= $f['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?>
        </select></div>
      <div><label>Username *</label><input name="username" required maxlength="30" value="<?= e($f['username']) ?>"></div>
    </div>
    <label>Address *</label><input name="address" required maxlength="255" value="<?= e($f['address']) ?>">
    <div class="form-row">
      <div><label>Password * <span class="hint">(min. 8 characters)</span></label><input type="password" name="password" required></div>
      <div><label>Confirm Password *</label><input type="password" name="password2" required></div>
    </div>
    <p><button class="btn" type="submit">Register</button> <a href="<?= BASE_URL ?>/login.php">Already have an account?</a></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
