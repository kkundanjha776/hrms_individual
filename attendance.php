<?php
require_once __DIR__ . '/core.php';
hrRequireCsrf();
$currentEmployee = hrCurrentEmployee($hrDb);
if ($currentEmployee && strtoupper((string)($currentEmployee['role'] ?? '')) === 'EMPLOYEE') {
    header('Location: employee_dashboard.php');
    exit;
}

$companyId = hrCompanyId($hrDb);
$month = (string)($_GET['month'] ?? $_POST['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
$monthStart = $month . '-01';
$daysInMonth = (int)date('t', strtotime($monthStart));
$monthEnd = date('Y-m-t', strtotime($monthStart));

function hrAttendanceUpsert(mysqli $db, int $companyId, int $employeeId, string $date, string $status, ?string $checkIn = null, ?string $checkOut = null): void {
    $stmt = $db->prepare('INSERT INTO hr_attendance (company_id, employee_id, attendance_date, status, check_in, check_out) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), check_in = COALESCE(VALUES(check_in), check_in), check_out = COALESCE(VALUES(check_out), check_out)');
    $stmt->bind_param('iissss', $companyId, $employeeId, $date, $status, $checkIn, $checkOut);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $date = (string)($_POST['attendance_date'] ?? '');
    $action = (string)($_POST['action'] ?? '');
    if ($employeeId > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && str_starts_with($date, $month)) {
        if ($action === 'status') {
            $status = (string)($_POST['status'] ?? 'Present');
            if (in_array($status, ['Present', 'Absent', 'Paid Leave', 'Work From Home', 'Late'], true)) {
                hrAttendanceUpsert($hrDb, $companyId, $employeeId, $date, $status);
                hrFlash('Attendance updated.');
            }
        } elseif ($action === 'checkin' || $action === 'checkout') {
            $time = date('H:i:s');
            $type = $action === 'checkin' ? 'check_in' : 'check_out';
            $stmt = $hrDb->prepare('INSERT INTO hr_attendance_logs (company_id, employee_id, log_date, punch_type, punch_time, source) VALUES (?, ?, ?, ?, ?, "manual")');
            $stmt->bind_param('iisss', $companyId, $employeeId, $date, $type, $time);
            $stmt->execute();
            $stmt->close();
            hrAttendanceUpsert($hrDb, $companyId, $employeeId, $date, 'Present', $action === 'checkin' ? $time : null, $action === 'checkout' ? $time : null);
            hrFlash($action === 'checkin' ? 'Check-in recorded.' : 'Check-out recorded.');
        } elseif ($action === 'holiday') {
            $stmt = $hrDb->prepare("INSERT IGNORE INTO hr_holidays (company_id, state_name, holiday_name, holiday_date, repeat_annually, description) VALUES (?, '', 'Company holiday', ?, 0, 'Marked from monthly attendance grid')");
            $stmt->bind_param('is', $companyId, $date);
            $stmt->execute();
            $stmt->close();
            hrFlash('Holiday marked.');
        }
    }
    header('Location: attendance.php?month=' . urlencode($month));
    exit;
}

$employees = $hrDb->query("SELECT id, employee_code, first_name, last_name, department FROM hr_employees WHERE company_id = $companyId AND status = 'Active' ORDER BY first_name, last_name")->fetch_all(MYSQLI_ASSOC);
$attendanceRows = $hrDb->query("SELECT employee_id, attendance_date, status, check_in, check_out FROM hr_attendance WHERE company_id = $companyId AND attendance_date BETWEEN '$monthStart' AND '$monthEnd'")->fetch_all(MYSQLI_ASSOC);
$attendanceMap = [];
foreach ($attendanceRows as $row) $attendanceMap[(int)$row['employee_id']][(string)$row['attendance_date']] = $row;
$holidayRows = $hrDb->query("SELECT holiday_date, holiday_name FROM hr_holidays WHERE company_id = $companyId AND holiday_date BETWEEN '$monthStart' AND '$monthEnd'")->fetch_all(MYSQLI_ASSOC);
$holidayMap = [];
foreach ($holidayRows as $row) $holidayMap[(string)$row['holiday_date']] = (string)$row['holiday_name'];
$previousMonth = date('Y-m', strtotime($monthStart . ' -1 month'));
$nextMonth = date('Y-m', strtotime($monthStart . ' +1 month'));
hrPageStart('Attendance');
?>
<div class="page-head">
    <div><h2>Monthly attendance</h2><p>Review and update every employee day by day.</p></div>
    <form method="get" class="form-actions"><input type="month" name="month" value="<?= htmlspecialchars($month) ?>"><button class="button secondary">Load month</button></form>
</div>
<div class="panel attendance-grid-panel">
    <div class="attendance-grid-toolbar">
        <a class="button secondary" href="attendance.php?month=<?= htmlspecialchars($previousMonth) ?>">Previous</a>
        <strong><?= htmlspecialchars(date('F Y', strtotime($monthStart))) ?></strong>
        <a class="button secondary" href="attendance.php?month=<?= htmlspecialchars($nextMonth) ?>">Next</a>
        <span class="attendance-legend"><span class="legend-dot present-dot"></span>Present <span class="legend-dot absent-dot"></span>Absent <span class="legend-dot holiday-dot"></span>Holiday <span class="legend-dot open-dot"></span>Unmarked</span>
    </div>
    <div class="table-wrap attendance-grid-wrap">
        <table class="attendance-grid"><thead><tr><th class="employee-heading">Employee</th>
            <?php for ($day = 1; $day <= $daysInMonth; $day++): $date = sprintf('%s-%02d', $month, $day); ?><th class="day-heading"><span><?= $day ?></span><small><?= date('D', strtotime($date)) ?></small></th><?php endfor; ?>
        </tr></thead><tbody>
        <?php foreach ($employees as $employee): ?><tr>
            <th class="employee-cell"><b><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></b><small><?= htmlspecialchars($employee['employee_code']) ?></small></th>
            <?php for ($day = 1; $day <= $daysInMonth; $day++):
                $date = sprintf('%s-%02d', $month, $day);
                $record = $attendanceMap[(int)$employee['id']][$date] ?? null;
                $isHoliday = isset($holidayMap[$date]);
                $isWeeklyOff = hrIsWeeklyOffForEmployee($hrDb, $companyId, (int)$employee['id'], $date);
                $state = $isHoliday ? 'Holiday' : ($record['status'] ?? ($isWeeklyOff ? 'Weekly off' : 'Unmarked'));
                $stateClass = strtolower(str_replace(' ', '-', $state));
            ?><td class="attendance-day <?= htmlspecialchars($stateClass) ?>">
                <span class="attendance-state"><?= htmlspecialchars($state === 'Paid Leave' ? 'Leave' : $state) ?></span>
                <?php if ($record && ($record['check_in'] || $record['check_out'])): ?><small><?= htmlspecialchars(substr((string)$record['check_in'], 0, 5)) ?>-<?= htmlspecialchars(substr((string)$record['check_out'], 0, 5)) ?></small><?php endif; ?>
                <details><summary>edit</summary>
                    <form method="post"><input type="hidden" name="csrf" value="<?= hrCsrf() ?>"><input type="hidden" name="month" value="<?= htmlspecialchars($month) ?>"><input type="hidden" name="employee_id" value="<?= (int)$employee['id'] ?>"><input type="hidden" name="attendance_date" value="<?= htmlspecialchars($date) ?>"><input type="hidden" name="action" value="status"><select name="status"><option>Present</option><option>Absent</option><option>Paid Leave</option><option>Work From Home</option><option>Late</option></select><button class="mini-action">Save</button></form>
                    <form method="post"><input type="hidden" name="csrf" value="<?= hrCsrf() ?>"><input type="hidden" name="month" value="<?= htmlspecialchars($month) ?>"><input type="hidden" name="employee_id" value="<?= (int)$employee['id'] ?>"><input type="hidden" name="attendance_date" value="<?= htmlspecialchars($date) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="Present"><button class="mini-action">Present</button></form>
                    <form method="post"><input type="hidden" name="csrf" value="<?= hrCsrf() ?>"><input type="hidden" name="month" value="<?= htmlspecialchars($month) ?>"><input type="hidden" name="employee_id" value="<?= (int)$employee['id'] ?>"><input type="hidden" name="attendance_date" value="<?= htmlspecialchars($date) ?>"><button class="mini-action" name="action" value="checkin">In</button><button class="mini-action" name="action" value="checkout">Out</button></form>
                    <form method="post"><input type="hidden" name="csrf" value="<?= hrCsrf() ?>"><input type="hidden" name="month" value="<?= htmlspecialchars($month) ?>"><input type="hidden" name="employee_id" value="<?= (int)$employee['id'] ?>"><input type="hidden" name="attendance_date" value="<?= htmlspecialchars($date) ?>"><button class="mini-action" name="action" value="holiday">Holiday</button></form>
                </details>
            </td><?php endfor; ?>
        </tr><?php endforeach; ?></tbody></table>
    </div>
</div>