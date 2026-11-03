<?php
$pageTitle = 'Book Appointment'; $area = 'patient';
require_once __DIR__ . '/../includes/functions.php';
require_patient();

$deptId = (int) ($_GET['dept'] ?? 0);
$docId  = (int) ($_GET['doctor'] ?? 0);
$dept = $doctor = null; $doctors = [];

$departments = $pdo->query('SELECT * FROM departments ORDER BY department_name')->fetchAll();
if ($deptId) {
    $st = $pdo->prepare('SELECT * FROM departments WHERE department_id = ?'); $st->execute([$deptId]); $dept = $st->fetch();
    if ($dept) {
        $st = $pdo->prepare("SELECT * FROM doctors WHERE department_id = ? AND status = 'Available' ORDER BY doctor_name");
        $st->execute([$deptId]); $doctors = $st->fetchAll();
    }
}
if ($dept && $docId) {
    $st = $pdo->prepare("SELECT * FROM doctors WHERE doctor_id = ? AND department_id = ? AND status = 'Available'");
    $st->execute([$docId, $deptId]); $doctor = $st->fetch();
}
$step = $doctor ? 3 : ($dept ? 2 : 1);
require __DIR__ . '/../includes/header.php';
?>
<h1>Book an Appointment</h1>
<ul class="steps">
  <li class="<?= $step > 1 ? 'done' : 'on' ?>">Department</li>
  <li class="<?= $step > 2 ? 'done' : ($step == 2 ? 'on' : '') ?>">Doctor</li>
  <li class="<?= $step == 3 ? 'on' : '' ?>">Date and slot</li>
  <li>Confirm</li>
</ul>

<?php if ($step === 1): ?>
  <h2>Select a department</h2>
  <div class="grid g3">
  <?php foreach ($departments as $d): ?>
    <a class="choice dept" href="book.php?dept=<?= (int) $d['department_id'] ?>">
      <span class="ico"><?= dept_icon($d['department_name']) ?></span>
      <b><?= e($d['department_name']) ?></b><small><?= e($d['description']) ?></small></a>
  <?php endforeach; ?>
  </div>
  <?php if (!$departments): ?><p class="empty">No departments available.</p><?php endif; ?>

<?php elseif ($step === 2): ?>
  <p><a href="book.php">&larr; Change department</a></p>
  <h2>Doctors in <?= e($dept['department_name']) ?></h2>
  <div class="grid g3">
  <?php foreach ($doctors as $d): ?>
    <a class="choice doc" href="book.php?dept=<?= $deptId ?>&doctor=<?= (int) $d['doctor_id'] ?>">
      <span class="avatar" style="--h:<?= ((int) $d['doctor_id'] * 67) % 360 ?>"><?= e(initials($d['doctor_name'])) ?></span>
      <b><?= e($d['doctor_name']) ?></b>
      <small><?= e($d['specialization']) ?></small>
      <small>Available: <?= e(str_replace(',', ', ', $d['available_days'])) ?></small>
      <?php if ($d['clinic_room']): ?><small>Room: <?= e($d['clinic_room']) ?></small><?php endif; ?>
      <?php if ($d['consultation_fee'] !== null): ?><small>Fee: &#8369;<?= number_format((float) $d['consultation_fee'], 2) ?></small><?php endif; ?>
      <?php if ($d['experience_years'] !== null): ?><small><?= (int) $d['experience_years'] ?> yrs experience</small><?php endif; ?>
    </a>
  <?php endforeach; ?>
  </div>
  <?php if (!$doctors): ?><p class="empty">No available doctors in this department yet.</p><?php endif; ?>

<?php else: ?>
  <p><a href="book.php?dept=<?= $deptId ?>">&larr; Change doctor</a></p>
  <div class="card">
    <h2><?= e($doctor['doctor_name']) ?> <small class="hint">&middot; <?= e($dept['department_name']) ?></small></h2>
    <p class="hint">Works: <?= e(str_replace(',', ', ', $doctor['available_days'])) ?>. Select a highlighted date to see slot availability.</p>

    <div id="calendar" data-doctor="<?= (int) $doctor['doctor_id'] ?>" data-api="<?= BASE_URL ?>/patient/api_availability.php"></div>
    <div class="legend">
      <span><i style="background:#8fd3ac"></i>Available</span>
      <span><i style="background:#f0c66a"></i>Almost Full (&le; <?= ALMOST_FULL_AT ?> slots)</span>
      <span><i style="background:#eba3a3"></i>Fully Booked</span>
      <span><i style="background:#dfe7e9"></i>No schedule</span>
    </div>

    <div id="slotPanel" class="slot-panel" hidden aria-live="polite">
      <h3 id="spDate"></h3>
      <div class="ringrow">
        <svg class="ring" viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="42" class="ring-bg"/><circle id="spRing" cx="50" cy="50" r="42" class="ring-fg" stroke-dasharray="264" stroke-dashoffset="264"/></svg>
        <div class="slot-nums">
          <div><span class="hint">Total slots</span><b id="spTotal"></b></div>
          <div><span class="hint">Used</span><b id="spUsed"></b></div>
          <div><span class="hint">Available</span><b id="spAvail"></b></div>
        </div>
      </div>
      <p><span id="spStatus"></span> <span class="hint" id="spHours"></span></p>
      <a id="spContinue" class="btn" href="#">Book this date</a>
    </div>
  </div>
  <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
