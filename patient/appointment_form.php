<?php
$pageTitle = 'Appointment Form'; $area = 'patient';
require_once __DIR__ . '/../includes/functions.php';
require_patient();
$uid = (int) $_SESSION['user_id'];
$sid = (int) ($_GET['schedule_id'] ?? $_POST['schedule_id'] ?? 0);

$st = $pdo->prepare("SELECT s.*, d.doctor_name, d.clinic_room, d.consultation_fee, d.status AS doc_status, dp.department_name
    FROM schedules s JOIN doctors d ON d.doctor_id = s.doctor_id JOIN departments dp ON dp.department_id = d.department_id
    WHERE s.schedule_id = ?");
$st->execute([$sid]);
$sch = $st->fetch();
if (!$sch || $sch['doc_status'] !== 'Available' || $sch['appointment_date'] < date('Y-m-d')) {
    flash('error', 'That schedule is not available.');
    redirect('/patient/book.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $reason = mb_substr(post('reason'), 0, 255);
    [$ok, $msg, $id] = book_slot($pdo, $uid, $sid, $reason);
    if ($ok) redirect('/patient/appointment_confirmation.php?id=' . $id);
    flash('error', $msg);
    redirect('/patient/appointment_form.php?schedule_id=' . $sid);
}

$max = min(MAX_SLOTS_PER_DAY, (int) $sch['max_slots']);
$used = used_slots($pdo, $sid);
$avail = max(0, $max - $used);
$next = free_slot($pdo, $sid, $max);

$st = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE schedule_id = ? AND user_id = ? AND status <> 'Cancelled'");
$st->execute([$sid, $uid]);
$dup = $st->fetchColumn() > 0;

$st = $pdo->prepare('SELECT name, patient_id FROM users WHERE user_id = ?');
$st->execute([$uid]);
$me = $st->fetch();

require __DIR__ . '/../includes/header.php';
?>
<h1>Appointment Summary</h1>
<p><a href="book.php">&larr; Back to booking</a></p>
<div class="card summary" style="max-width:640px">
  <dl>
    <dt>Patient</dt><dd><?= e($me['name']) ?> (<?= e($me['patient_id']) ?>)</dd>
    <dt>Department</dt><dd><?= e($sch['department_name']) ?></dd>
    <dt>Doctor</dt><dd><?= e($sch['doctor_name']) ?></dd>
    <dt>Date</dt><dd><?= e(fmt_date($sch['appointment_date'])) ?></dd>
    <?php if ($next !== null): ?>
      <dt>Estimated time</dt><dd><?= e(fmt_time(slot_time($sch['start_time'], $sch['end_time'], $max, $next))) ?></dd>
      <dt>Slot number</dt><dd><?= str_pad((string) $next, 3, '0', STR_PAD_LEFT) ?> <span class="hint">(final number assigned on confirmation)</span></dd>
    <?php endif; ?>
    <dt>Slots</dt><dd><?= $max ?> total &middot; <?= $used ?> used &middot; <?= $avail ?> available</dd>
    <?php if ($sch['clinic_room']): ?><dt>Clinic room</dt><dd><?= e($sch['clinic_room']) ?></dd><?php endif; ?>
    <?php if ($sch['consultation_fee'] !== null): ?><dt>Consultation fee</dt><dd>&#8369;<?= number_format((float) $sch['consultation_fee'], 2) ?></dd><?php endif; ?>
  </dl>
  <hr style="border:0;border-top:1px solid var(--line);margin:16px 0">
  <?php if ($dup): ?>
    <div class="alert alert-info">You already have an active appointment on this date. See <a href="my_appointments.php">My Appointments</a>.</div>
  <?php elseif ($next === null): ?>
    <div class="alert alert-error"><b>FULLY BOOKED.</b> Please choose another date.</div>
  <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="schedule_id" value="<?= $sid ?>">
      <label>Reason for visit (optional)</label>
      <textarea name="reason" rows="3" maxlength="255" placeholder="e.g. fever, check-up, follow-up"></textarea>
      <p><button class="btn btn-ok" type="submit">Confirm Appointment</button>
         <a class="btn btn-outline" href="book.php">Cancel</a></p>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
