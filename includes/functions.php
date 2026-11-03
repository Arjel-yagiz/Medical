<?php
/**
 * Shared helpers: sessions, security, slot logic, notifications.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
require_once __DIR__ . '/../config/db.php';

const MAX_SLOTS_PER_DAY = 100;
const ALMOST_FULL_AT    = 20;   // 1..20 slots left = "Almost Full"

/* ------------------------------------------------------------ basics */
function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

function redirect(string $path): void { header('Location: ' . BASE_URL . $path); exit; }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        die('Invalid or expired form token. Go back, refresh the page and try again.');
    }
}

function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function show_flash(): void {
    foreach ($_SESSION['flash'] ?? [] as [$t, $m]) echo '<div class="alert flash alert-' . e($t) . '">' . e($m) . '</div>';
    unset($_SESSION['flash']);
}

function post(string $k): string { return trim((string) ($_POST[$k] ?? '')); }

/* ------------------------------------------------------ access control */
function is_patient(): bool { return !empty($_SESSION['user_id']); }
function is_admin(): bool   { return !empty($_SESSION['admin_id']); }
function is_doctor(): bool  { return !empty($_SESSION['doctor_id']); }
function require_patient(): void { if (!is_patient()) { flash('error', 'Please log in first.'); redirect('/login.php'); } }
function require_doctor(): void  { if (!is_doctor())  { flash('error', 'Doctor login required.'); redirect('/doctor/login.php'); } }
function require_admin(): void   { if (!is_admin())   { flash('error', 'Admin login required.'); redirect('/admin/login.php'); } }

function logout_and_redirect(string $to): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ' . BASE_URL . $to);
    exit;
}

/* ---------------------------------------------------------- formatting */
function fmt_date(string $d): string { return date('F j, Y', strtotime($d)); }
function fmt_time(string $t): string { return date('g:i A', strtotime($t)); }

function status_badge(string $s): string {
    $c = ['Pending' => 'warn', 'Confirmed' => 'info', 'Completed' => 'ok', 'Cancelled' => 'bad', 'No Show' => 'muted'][$s] ?? 'muted';
    return '<span class="badge badge-' . $c . '">' . e($s) . '</span>';
}

/* ---------------------------------------------------------- slot logic */
/** Number of slots currently occupied (everything except Cancelled). */
function used_slots(PDO $pdo, int $scheduleId): int {
    $st = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE schedule_id = ? AND status <> 'Cancelled'");
    $st->execute([$scheduleId]);
    return (int) $st->fetchColumn();
}

function availability_status(int $available, int $total): string {
    if ($available <= 0) return 'Fully Booked';
    if ($available <= ALMOST_FULL_AT) return 'Almost Full';
    return 'Available';
}

/** Time of a slot: the working window is split evenly across max_slots. */
function slot_time(string $start, string $end, int $max, int $slot): string {
    $s = strtotime($start); $e = strtotime($end);
    $step = max(1, ($e - $s) / max(1, $max));
    return date('H:i:s', (int) ($s + ($slot - 1) * $step));
}

/** Lowest free slot number (1..max) in a schedule, or null if full. */
function free_slot(PDO $pdo, int $scheduleId, int $max): ?int {
    $st = $pdo->prepare("SELECT slot_number FROM appointments WHERE schedule_id = ? AND status <> 'Cancelled'");
    $st->execute([$scheduleId]);
    $taken = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
    for ($i = 1; $i <= $max; $i++) if (!isset($taken[$i])) return $i;
    return null;
}

function appointment_no(string $date, int $slot): string {
    return 'APPT-' . date('Ymd', strtotime($date)) . '-' . str_pad((string) $slot, 3, '0', STR_PAD_LEFT);
}

/* ------------------------------------------------------- notifications */
function notify(PDO $pdo, int $userId, ?int $apptId, string $type, string $title, string $msg): void {
    $st = $pdo->prepare('INSERT INTO notifications (user_id, appointment_id, type, title, message) VALUES (?,?,?,?,?)');
    $st->execute([$userId, $apptId, $type, $title, $msg]);
}
function unread_count(PDO $pdo, int $userId): int {
    $st = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $st->execute([$userId]);
    return (int) $st->fetchColumn();
}

/** Creates "tomorrow" reminders once per appointment. Safe to call often. */
function generate_reminders(PDO $pdo): int {
    $rows = $pdo->query("SELECT a.appointment_id, a.user_id, a.appointment_time, d.doctor_name
                         FROM appointments a JOIN doctors d ON d.doctor_id = a.doctor_id
                         WHERE a.appointment_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                           AND a.status IN ('Pending','Confirmed') AND a.remind_sent = 0")->fetchAll();
    $mark = $pdo->prepare('UPDATE appointments SET remind_sent = 1 WHERE appointment_id = ?');
    foreach ($rows as $r) {
        notify($pdo, (int) $r['user_id'], (int) $r['appointment_id'], 'Reminder', 'Appointment Reminder',
            'You have an appointment with ' . $r['doctor_name'] . ' tomorrow at ' . fmt_time($r['appointment_time']) . '.');
        $mark->execute([$r['appointment_id']]);
    }
    return count($rows);
}

/* --------------------------------------------------------- appointments */
/**
 * Book the next free slot. Runs in a transaction and locks the schedule row,
 * so two patients can never get the same slot or exceed the maximum.
 * @return array [bool ok, string message, int|null appointment_id]
 */
function book_slot(PDO $pdo, int $userId, int $scheduleId, string $reason): array {
    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare('SELECT * FROM schedules WHERE schedule_id = ? FOR UPDATE');
        $st->execute([$scheduleId]);
        $sch = $st->fetch();
        if (!$sch) { $pdo->rollBack(); return [false, 'Schedule not found.', null]; }
        if ($sch['appointment_date'] < date('Y-m-d')) { $pdo->rollBack(); return [false, 'You cannot book a past date.', null]; }

        $st = $pdo->prepare('SELECT doctor_name, status FROM doctors WHERE doctor_id = ?');
        $st->execute([$sch['doctor_id']]);
        $doc = $st->fetch();
        if (!$doc || $doc['status'] !== 'Available') { $pdo->rollBack(); return [false, 'This doctor is not available.', null]; }

        $st = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE schedule_id = ? AND user_id = ? AND status <> 'Cancelled'");
        $st->execute([$scheduleId, $userId]);
        if ($st->fetchColumn() > 0) { $pdo->rollBack(); return [false, 'You already have an appointment for this schedule.', null]; }

        $max  = min(MAX_SLOTS_PER_DAY, (int) $sch['max_slots']);
        $slot = free_slot($pdo, $scheduleId, $max);
        if ($slot === null) { $pdo->rollBack(); return [false, 'This date is FULLY BOOKED. Please choose another date.', null]; }

        $time = slot_time($sch['start_time'], $sch['end_time'], $max, $slot);
        $no   = appointment_no($sch['appointment_date'], $slot);

        $ins = $pdo->prepare('INSERT INTO appointments
            (appointment_no, user_id, doctor_id, schedule_id, appointment_date, appointment_time, slot_number, reason, status)
            VALUES (?,?,?,?,?,?,?,?,\'Pending\')');
        $ins->execute([$no, $userId, $sch['doctor_id'], $scheduleId, $sch['appointment_date'], $time, $slot, $reason !== '' ? $reason : null]);
        $id = (int) $pdo->lastInsertId();

        notify($pdo, $userId, $id, 'Confirmed', 'Appointment Confirmed',
            'Your appointment with ' . $doc['doctor_name'] . ' on ' . fmt_date($sch['appointment_date']) .
            ' at ' . fmt_time($time) . ' has been successfully booked. Appointment No.: ' . $no . '.');

        $pdo->commit();
        return [true, 'Appointment booked.', $id];
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($ex->getCode() === '23000') return [false, 'That slot was just taken. Please try again.', null];
        return [false, 'Could not save the appointment. Please try again.', null];
    }
}

/** Cancel an appointment (frees the slot). */
function cancel_appointment(PDO $pdo, int $id, string $by = 'patient'): bool {
    $st = $pdo->prepare('SELECT a.*, d.doctor_name FROM appointments a JOIN doctors d ON d.doctor_id = a.doctor_id WHERE a.appointment_id = ?');
    $st->execute([$id]);
    $a = $st->fetch();
    if (!$a || !in_array($a['status'], ['Pending', 'Confirmed'], true)) return false;

    // appointment_no gets a suffix so the number can be re-used by the next patient who takes the slot
    $up = $pdo->prepare("UPDATE appointments SET status = 'Cancelled', slot_number = NULL, appointment_no = CONCAT(appointment_no, '-X', appointment_id) WHERE appointment_id = ?");
    $up->execute([$id]);
    notify($pdo, (int) $a['user_id'], $id, 'Cancelled', 'Appointment Cancelled',
        'Your appointment scheduled for ' . fmt_date($a['appointment_date']) . ' with ' . $a['doctor_name'] . ' has been cancelled' .
        ($by === 'admin' ? ' by the clinic staff.' : '.'));
    return true;
}

/** Move an appointment to another schedule of the same doctor. */
function reschedule_appointment(PDO $pdo, int $id, int $newScheduleId): array {
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT * FROM schedules WHERE schedule_id = ? FOR UPDATE');
        $st->execute([$newScheduleId]);
        $sch = $st->fetch();

        $st = $pdo->prepare('SELECT a.*, d.doctor_name FROM appointments a JOIN doctors d ON d.doctor_id = a.doctor_id WHERE a.appointment_id = ? FOR UPDATE');
        $st->execute([$id]);
        $a = $st->fetch();

        if (!$sch || !$a || !in_array($a['status'], ['Pending', 'Confirmed'], true)) { $pdo->rollBack(); return [false, 'Appointment cannot be rescheduled.']; }
        if ((int) $sch['doctor_id'] !== (int) $a['doctor_id']) { $pdo->rollBack(); return [false, 'Choose a schedule of the same doctor.']; }
        if ((int) $sch['schedule_id'] === (int) $a['schedule_id']) { $pdo->rollBack(); return [false, 'Choose a different date.']; }
        if ($sch['appointment_date'] < date('Y-m-d')) { $pdo->rollBack(); return [false, 'Cannot move to a past date.']; }

        $st = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE schedule_id = ? AND user_id = ? AND status <> 'Cancelled'");
        $st->execute([$newScheduleId, $a['user_id']]);
        if ($st->fetchColumn() > 0) { $pdo->rollBack(); return [false, 'Patient already has an appointment on that date.']; }

        $max  = min(MAX_SLOTS_PER_DAY, (int) $sch['max_slots']);
        $slot = free_slot($pdo, $newScheduleId, $max);
        if ($slot === null) { $pdo->rollBack(); return [false, 'That date is fully booked.']; }

        $time = slot_time($sch['start_time'], $sch['end_time'], $max, $slot);
        $up = $pdo->prepare('UPDATE appointments SET schedule_id = ?, appointment_date = ?, appointment_time = ?, slot_number = ?,
                             appointment_no = ?, remind_sent = 0 WHERE appointment_id = ?');
        $up->execute([$newScheduleId, $sch['appointment_date'], $time, $slot, appointment_no($sch['appointment_date'], $slot), $id]);

        notify($pdo, (int) $a['user_id'], $id, 'Updated', 'Appointment Updated',
            'Your appointment schedule has been changed to ' . fmt_date($sch['appointment_date']) . ' at ' . fmt_time($time) . '.');
        $pdo->commit();
        return [true, 'Appointment rescheduled.'];
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, 'Could not reschedule. Please try again.'];
    }
}

/** Confirm / Complete / No Show with the matching notification. */
function set_status(PDO $pdo, int $id, string $status): bool {
    if (!in_array($status, ['Confirmed', 'Completed', 'No Show'], true)) return false;
    $st = $pdo->prepare('SELECT a.*, d.doctor_name FROM appointments a JOIN doctors d ON d.doctor_id = a.doctor_id WHERE a.appointment_id = ?');
    $st->execute([$id]);
    $a = $st->fetch();
    if (!$a || in_array($a['status'], ['Cancelled', 'Completed'], true)) return false;

    $pdo->prepare('UPDATE appointments SET status = ? WHERE appointment_id = ?')->execute([$status, $id]);
    $when = fmt_date($a['appointment_date']);
    $map = [
        'Confirmed' => ['Confirmed', 'Appointment Confirmed', "Your appointment with {$a['doctor_name']} on $when has been confirmed by the clinic."],
        'Completed' => ['Info', 'Appointment Completed', "Your appointment with {$a['doctor_name']} on $when was marked as completed. Thank you!"],
        'No Show'   => ['Info', 'Missed Appointment', "Your appointment with {$a['doctor_name']} on $when was marked as a no-show."],
    ];
    [$type, $title, $msg] = $map[$status];
    notify($pdo, (int) $a['user_id'], $id, $type, $title, $msg);
    return true;
}

/* ------------------------------------------------------------------ ui */
function dept_icon(string $name): string {
    $n = strtolower($name);
    foreach (['pedia' => '🧸', 'ob' => '🌸', 'gyn' => '🌸', 'cardio' => '🫀', 'derma' => '🧴', 'internal' => '🩺'] as $k => $i) if (strpos($n, $k) !== false) return $i;
    return '➕';
}
function initials(string $name): string {
    $p = preg_split('/\s+/', trim(preg_replace('/^(Dr\.?)\s*/i', '', $name)));
    return strtoupper(mb_substr($p[0] ?? '', 0, 1) . mb_substr(end($p) ?: '', 0, 1));
}

/** Small POST button used in appointment tables. $target = page that handles the POST. */
function action_btn(string $target, int $id, string $action, string $label, string $cls = '', string $confirm = ''): string {
    return '<form method="post" action="' . e(BASE_URL . $target) . '" class="inline-form"' . ($confirm ? ' onsubmit="return confirm(\'' . e($confirm) . '\')"' : '') . '>' . csrf_field() .
        '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="appointment_id" value="' . $id . '">' .
        '<button class="btn btn-sm ' . $cls . '">' . e($label) . '</button></form> ';
}
