<?php
// JSON: slot availability of one doctor for one month. Everything comes from the database.
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
if (!is_patient()) { http_response_code(401); echo json_encode(['error' => 'Not logged in']); exit; }

$doctorId = (int) ($_GET['doctor_id'] ?? 0);
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
$first = $month . '-01';
$last = date('Y-m-t', strtotime($first));

$st = $pdo->prepare("SELECT s.schedule_id, s.appointment_date, s.start_time, s.end_time, s.max_slots,
      (SELECT COUNT(*) FROM appointments a WHERE a.schedule_id = s.schedule_id AND a.status <> 'Cancelled') AS used
    FROM schedules s JOIN doctors d ON d.doctor_id = s.doctor_id
    WHERE s.doctor_id = ? AND d.status = 'Available' AND s.appointment_date BETWEEN ? AND ?");
$st->execute([$doctorId, $first, $last]);

$out = [];
foreach ($st->fetchAll() as $r) {
    $total = min(MAX_SLOTS_PER_DAY, (int) $r['max_slots']);
    $used = (int) $r['used'];
    $avail = max(0, $total - $used);
    $out[$r['appointment_date']] = [
        'schedule_id' => (int) $r['schedule_id'],
        'total' => $total, 'used' => $used, 'available' => $avail,
        'status' => availability_status($avail, $total),
        'start' => fmt_time($r['start_time']), 'end' => fmt_time($r['end_time']),
        'past' => $r['appointment_date'] < date('Y-m-d'),
    ];
}
echo json_encode(['month' => $month, 'today' => date('Y-m-d'), 'dates' => $out]);
