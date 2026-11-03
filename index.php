<?php
$pageTitle = 'Home'; $area = 'public';
require __DIR__ . '/includes/header.php';
$depts = $pdo->query("SELECT d.department_id, d.department_name, d.description,
            (SELECT COUNT(*) FROM doctors x WHERE x.department_id = d.department_id AND x.status = 'Available') AS doctors
          FROM departments d ORDER BY d.department_name")->fetchAll();
?>
<section class="hero">
  <div>
    <h1>Book a doctor. Get your queue number.</h1>
    <p>Pick a department, see how many slots are left on each day, and walk in with a number instead of waiting in line.</p>
    <div class="chips" role="group" aria-label="Try a department">
    <?php foreach ($depts as $i => $d): ?>
      <button type="button" class="chip<?= $i === 0 ? ' on' : '' ?>" data-name="<?= e($d['department_name']) ?>" data-docs="<?= (int) $d['doctors'] ?>"><?= e(dept_icon($d['department_name'])) ?> <?= e(preg_replace('/\s*\(.*\)/', '', $d['department_name'])) ?></button>
    <?php endforeach; ?>
    </div>
    <?php if (is_patient()): ?>
      <a class="btn" href="<?= BASE_URL ?>/patient/book.php">Book an appointment</a>
    <?php else: ?>
      <a class="btn" href="<?= BASE_URL ?>/register.php">Create an account</a>
      <a class="btn btn-outline" href="<?= BASE_URL ?>/login.php">I already have one</a>
    <?php endif; ?>
  </div>
  <div class="tilt"><div class="stub" id="stub">
    <div class="top-r"><span>Sample ticket</span><span><?= date('M j, Y') ?></span></div>
    <div class="big" id="stubNo">038</div>
    <div class="dept" id="stubDept"><?= e($depts[0]['department_name'] ?? 'Choose a department') ?></div>
    <hr>
    <div class="foot"><span id="stubDocs"><?= (int) ($depts[0]['doctors'] ?? 0) ?> doctors</span><span>APPT-<?= date('Ymd') ?>-038</span></div>
  </div></div>
</section>

<h2>Our Departments</h2>
<div class="grid g3">
<?php foreach ($depts as $d): ?>
  <div class="card" style="margin:0">
    <h3><?= dept_icon($d['department_name']) ?> <?= e($d['department_name']) ?></h3>
    <p class="hint"><?= e($d['description']) ?></p>
    <span class="badge badge-info"><?= (int) $d['doctors'] ?> doctor(s)</span>
  </div>
<?php endforeach; ?>
<?php if (!$depts): ?><p class="empty">No departments yet.</p><?php endif; ?>
</div>

<h2 style="margin-top:30px">What happens after you book</h2>
<div class="grid g4">
  <div class="stat"><b>1</b><span>Create an account and log in</span></div>
  <div class="stat"><b>2</b><span>Choose department &amp; doctor</span></div>
  <div class="stat"><b>3</b><span>Pick a date with open slots</span></div>
  <div class="stat"><b>4</b><span>Get your appointment number and a notification</span></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
