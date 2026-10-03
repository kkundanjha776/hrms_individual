<?php
require_once __DIR__.'/core.php';
hrSetNoCacheHeaders();
hrRequireEmployeeLogin();

$employee = hrCurrentEmployee($hrDb);
if (!$employee) {
    header('Location: employee_login.php');
    exit;
}

$today = date('Y-m-d');
$employeeRole = strtoupper((string)($employee['role'] ?? 'Employee'));
$attendance = $hrDb->query('SELECT * FROM hr_attendance WHERE employee_id='.(int)$employee['id'].' AND attendance_date="'.$today.'" LIMIT 1')->fetch_assoc();
$attendanceSummary = hrAttendanceSummaryForDate($hrDb, (int)$employee['id'], $today);
$companyId = (int)$employee['company_id'];
$canMarkAttendance = in_array($employeeRole, ['EMPLOYEE', 'HR'], true);
$canViewExecutive = in_array($employeeRole, ['HR', 'MD'], true);
$canManageAssets = in_array($employeeRole, ['IT', 'HR', 'MD'], true);

$allEmployees = [];
if ($canViewExecutive) {
    $allEmployees = $hrDb->query("SELECT * FROM hr_employees WHERE company_id=$companyId ORDER BY first_name, last_name")->fetch_all(MYSQLI_ASSOC);
}

$assetAssignments = [];
if ($canManageAssets) {
    $assetAssignments = $hrDb->query("SELECT a.*, e.first_name, e.last_name, e.employee_code FROM hr_employee_assets a JOIN hr_employees e ON e.id = a.employee_id WHERE a.company_id=$companyId ORDER BY a.assigned_date DESC")->fetch_all(MYSQLI_ASSOC);
}

$companyMetrics = [
    'totalEmployees' => (int)($hrDb->query("SELECT COUNT(*) AS n FROM hr_employees WHERE company_id=$companyId AND status='Active'")->fetch_assoc()['n'] ?? 0),
    'presentToday' => (int)($hrDb->query("SELECT COUNT(*) AS n FROM hr_attendance WHERE company_id=$companyId AND attendance_date='$today' AND status IN ('Present','Late','Paid Leave','Work From Home')")->fetch_assoc()['n'] ?? 0),
    'absentToday' => (int)($hrDb->query("SELECT COUNT(*) AS n FROM hr_employees e LEFT JOIN hr_attendance a ON a.employee_id = e.id AND a.attendance_date = '$today' WHERE e.company_id=$companyId AND e.status='Active' AND (a.id IS NULL OR a.status='Absent')")->fetch_assoc()['n'] ?? 0),
    'unloggedToday' => (int)($hrDb->query("SELECT COUNT(*) AS n FROM hr_employees e LEFT JOIN hr_attendance a ON a.employee_id = e.id AND a.attendance_date = '$today' WHERE e.company_id=$companyId AND e.status='Active' AND a.id IS NULL")->fetch_assoc()['n'] ?? 0),
    'pendingLeave' => (int)($hrDb->query("SELECT COUNT(*) AS n FROM hr_leave_requests WHERE company_id=$companyId AND status='Pending'")->fetch_assoc()['n'] ?? 0),
    'leaveApprovedThisMonth' => (int)($hrDb->query("SELECT COUNT(*) AS n FROM hr_leave_requests WHERE company_id=$companyId AND status='Approved' AND DATE_FORMAT(start_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')")->fetch_assoc()['n'] ?? 0),
    'lateToday' => (int)($hrDb->query("SELECT COUNT(*) AS n FROM hr_attendance WHERE company_id=$companyId AND attendance_date='$today' AND status='Late'")->fetch_assoc()['n'] ?? 0)
];

$monthlyCost = (float)($hrDb->query("SELECT COALESCE(SUM(basic_salary + hra + allowance), 0) AS total FROM hr_employees WHERE company_id=$companyId AND status='Active'")->fetch_assoc()['total'] ?? 0);
$monthlyTrend = [];
for ($i = 5; $i >= 0; $i--) {
    $monthDate = date('Y-m', strtotime("-$i months"));
    $monthLabel = date('M', strtotime("-$i months"));
    $presentCount = (int)($hrDb->query("SELECT COUNT(*) AS n FROM hr_attendance WHERE company_id=$companyId AND DATE_FORMAT(attendance_date, '%Y-%m') = '$monthDate' AND status IN ('Present','Late','Paid Leave','Work From Home')")->fetch_assoc()['n'] ?? 0);
    $monthlyTrend[] = ['label' => $monthLabel, 'present' => $presentCount];
}
$trendMax = max(array_column($monthlyTrend, 'present')) ?: 1;
$trendPoints = [];
foreach ($monthlyTrend as $index => $item) {
    $x = 20 + ($index * 58);
    $y = 120 - (($item['present'] / $trendMax) * 80);
    $trendPoints[] = $x . ',' . $y;
}
$trendSvg = implode(' ', $trendPoints);

$absentList = [];
$unloggedList = [];
$leaveList = [];
if (in_array($employeeRole, ['HR', 'MD'], true)) {
    $absentList = $hrDb->query("SELECT e.first_name, e.last_name, e.employee_code, e.department, COALESCE(a.status, 'Absent') as status FROM hr_employees e LEFT JOIN hr_attendance a ON a.employee_id = e.id AND a.attendance_date = '$today' WHERE e.company_id = $companyId AND e.status = 'Active' AND (a.id IS NULL OR a.status = 'Absent') ORDER BY e.first_name")->fetch_all(MYSQLI_ASSOC);
    $unloggedList = $hrDb->query("SELECT e.first_name, e.last_name, e.employee_code, e.department FROM hr_employees e LEFT JOIN hr_attendance a ON a.employee_id = e.id AND a.attendance_date = '$today' WHERE e.company_id = $companyId AND e.status = 'Active' AND a.id IS NULL ORDER BY e.first_name")->fetch_all(MYSQLI_ASSOC);
    $leaveList = $hrDb->query("SELECT e.first_name, e.last_name, e.employee_code, l.leave_type, l.start_date, l.end_date, l.status FROM hr_leave_requests l JOIN hr_employees e ON e.id = l.employee_id WHERE l.company_id = $companyId AND l.status = 'Approved' AND l.start_date <= '$today' AND l.end_date >= '$today' ORDER BY e.first_name")->fetch_all(MYSQLI_ASSOC);
}

$calendarMonth = (string)($_GET['calendar_month'] ?? date('Y-m'));
$selectedDate = (string)($_GET['selected_date'] ?? $today);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = $today;
}
$calendarStamp = strtotime($calendarMonth . '-01');
$calendarStart = date('Y-m-01', $calendarStamp);
$calendarLabel = date('F Y', $calendarStamp);
$calendarPrev = date('Y-m', strtotime('-1 month', $calendarStamp));
$calendarNext = date('Y-m', strtotime('+1 month', $calendarStamp));
$daysInMonth = (int)date('t', $calendarStamp);
$firstWeekday = (int)date('N', strtotime($calendarStart));
$calendarDays = [];
for ($i = 1; $i < $firstWeekday; $i++) { $calendarDays[] = ['day' => '', 'date' => '', 'holidays' => []]; }
for ($day = 1; $day <= $daysInMonth; $day++) {
    $dateKey = date('Y-m-d', strtotime($calendarStart . ' + ' . ($day - 1) . ' days'));
    $calendarDays[] = ['day' => (string)$day, 'date' => $dateKey, 'holidays' => []];
}
while (count($calendarDays) % 7 !== 0) {
    $calendarDays[] = ['day' => '', 'date' => '', 'holidays' => []];
}
$calendarHolidayRows = $hrDb->query("SELECT * FROM hr_holidays WHERE company_id=$companyId AND DATE_FORMAT(holiday_date, '%Y-%m') = '" . date('Y-m', $calendarStamp) . "' ORDER BY holiday_date ASC")->fetch_all(MYSQLI_ASSOC);
$holidayMap = [];
foreach ($calendarHolidayRows as $holiday) {
    $holidayDate = (string)($holiday['holiday_date'] ?? '');
    if ($holidayDate === '') { continue; }
    $holidayMap[$holidayDate][] = $holiday;
}
foreach ($calendarDays as $index => $item) {
    if ($item['date'] !== '') {
        $dateKey = $item['date'];
        $calendarDays[$index]['holidays'] = $holidayMap[$dateKey] ?? [];
        $calendarDays[$index]['attendance'] = hrAttendanceSummaryForDate($hrDb, (int)$employee['id'], $dateKey);
        $calendarDays[$index]['weekly_off'] = hrIsWeeklyOffForEmployee($hrDb, $companyId, (int)$employee['id'], $dateKey);
    } else {
        $calendarDays[$index]['attendance'] = ['check_in' => null, 'check_out' => null, 'total_hours' => '0:00'];
        $calendarDays[$index]['weekly_off'] = false;
    }
}

$selectedDayLogs = $hrDb->query("SELECT punch_type, punch_time, source, created_at FROM hr_attendance_logs WHERE company_id = $companyId AND employee_id = " . (int)$employee['id'] . " AND log_date = '$selectedDate' ORDER BY punch_time ASC")->fetch_all(MYSQLI_ASSOC);
$selectedDaySummary = hrAttendanceSummaryForDate($hrDb, (int)$employee['id'], $selectedDate);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hrRequireCsrf();
    $action = $_POST['action'] ?? '';

    if ($canMarkAttendance && $action === 'checkin') {
        $punchTime = date('H:i:s');
        $hrDb->query("INSERT INTO hr_attendance_logs (company_id, employee_id, log_date, punch_type, punch_time, source) VALUES ($companyId, " . (int)$employee['id'] . ", '$today', 'check_in', '$punchTime', 'manual')");

        $summary = hrAttendanceSummaryForDate($hrDb, (int)$employee['id'], $today);
        $status = hrIsWeeklyOffForEmployee($hrDb, $companyId, (int)$employee['id'], $today) ? 'Paid Leave' : 'Present';
        $checkIn = $summary['check_in'];
        $checkOut = $summary['check_out'];
        $upsert = $hrDb->query("INSERT INTO hr_attendance (company_id, employee_id, attendance_date, status, check_in, check_out) VALUES ($companyId, " . (int)$employee['id'] . ", '$today', '$status', " . ($checkIn !== null ? "'$checkIn'" : "NULL") . ", " . ($checkOut !== null ? "'$checkOut'" : "NULL") . ") ON DUPLICATE KEY UPDATE status = VALUES(status), check_in = COALESCE(VALUES(check_in), check_in), check_out = COALESCE(VALUES(check_out), check_out)");
        if ($upsert === false) {
            hrFlash('Unable to record check-in.', 'error');
        } else {
            hrFlash('Check-in recorded successfully.');
        }
    } elseif ($canMarkAttendance && $action === 'checkout') {
        $attendanceRow = $hrDb->query('SELECT * FROM hr_attendance WHERE employee_id='.(int)$employee['id'].' AND attendance_date="'.$today.'" LIMIT 1')->fetch_assoc();
        if ($attendanceRow) {
            $punchTime = date('H:i:s');
            $hrDb->query("INSERT INTO hr_attendance_logs (company_id, employee_id, log_date, punch_type, punch_time, source) VALUES ($companyId, " . (int)$employee['id'] . ", '$today', 'check_out', '$punchTime', 'manual')");
            $summary = hrAttendanceSummaryForDate($hrDb, (int)$employee['id'], $today);
            $status = hrIsWeeklyOffForEmployee($hrDb, $companyId, (int)$employee['id'], $today) ? 'Paid Leave' : 'Present';
            $checkIn = $summary['check_in'];
            $checkOut = $summary['check_out'];
            $upsert = $hrDb->query("INSERT INTO hr_attendance (company_id, employee_id, attendance_date, status, check_in, check_out) VALUES ($companyId, " . (int)$employee['id'] . ", '$today', '$status', " . ($checkIn !== null ? "'$checkIn'" : "NULL") . ", " . ($checkOut !== null ? "'$checkOut'" : "NULL") . ") ON DUPLICATE KEY UPDATE status = VALUES(status), check_in = COALESCE(VALUES(check_in), check_in), check_out = COALESCE(VALUES(check_out), check_out)");
            if ($upsert !== false) {
                hrFlash('Check-out recorded successfully.');
            } else {
                hrFlash('Unable to record check-out.', 'error');
            }
        } else {
            hrFlash('Please check in first.', 'error');
        }
    } elseif ($canViewExecutive && $action === 'terminate_employee') {
        $targetId = (int)($_POST['employee_id'] ?? 0);
        if ($targetId > 0 && $targetId !== (int)$employee['id']) {
            $hrDb->query('UPDATE hr_employees SET status = "Exited" WHERE id='.(int)$targetId.' AND company_id='.$companyId);
            hrFlash('Employee marked as terminated. You can undo this from the roster action button.');
        }
    } elseif ($canViewExecutive && $action === 'undo_termination') {
        $targetId = (int)($_POST['employee_id'] ?? 0);
        if ($targetId > 0 && $targetId !== (int)$employee['id']) {
            $hrDb->query('UPDATE hr_employees SET status = "Active" WHERE id='.(int)$targetId.' AND company_id='.$companyId);
            hrFlash('Termination reverted. Employee is active again.');
        }
    } elseif ($canManageAssets && $action === 'assign_asset') {
        $targetId = (int)($_POST['employee_id'] ?? 0);
        $assetType = trim((string)($_POST['asset_type'] ?? ''));
        $assetName = trim((string)($_POST['asset_name'] ?? ''));
        $serial = trim((string)($_POST['serial_number'] ?? ''));
        if ($targetId > 0 && $assetName !== '') {
            $assignedDate = date('Y-m-d');
            $hrDb->query("INSERT INTO hr_employee_assets (company_id, employee_id, asset_type, asset_name, serial_number, assigned_date, status, issued_by, notes) VALUES ($companyId, $targetId, '" . $hrDb->real_escape_string($assetType) . "', '" . $hrDb->real_escape_string($assetName) . "', '" . $hrDb->real_escape_string($serial) . "', '$assignedDate', 'Assigned', '" . $hrDb->real_escape_string($employee['first_name'] . ' ' . $employee['last_name']) . "', 'Allocated by IT')");
            hrFlash('IT asset allocated successfully.');
        }
    }

    header('Location: employee_dashboard.php');
    exit;
}

$attendance = $hrDb->query('SELECT * FROM hr_attendance WHERE employee_id='.(int)$employee['id'].' AND attendance_date="'.$today.'" LIMIT 1')->fetch_assoc();
hrPageStart($employeeRole === 'MD' ? 'Executive dashboard' : ($employeeRole === 'HR' ? 'HR dashboard' : 'Employee dashboard'), $employeeRole !== 'MD');
?>
<?php if ($canMarkAttendance): ?>
<div class="quick-attendance-bar">
    <div class="quick-attendance-main">
        <div class="quick-attendance-title">
            <span class="eyebrow-small">Today</span>
            <h3><?= htmlspecialchars(date('d M Y', strtotime($today))) ?></h3>
        </div>
        <div class="quick-punch-grid">
            <div class="quick-punch-box">
                <span>Check In</span>
                <strong><?= htmlspecialchars($attendanceSummary['check_in'] ?? '—') ?></strong>
            </div>
            <div class="quick-punch-box">
                <span>Check Out</span>
                <strong><?= htmlspecialchars($attendanceSummary['check_out'] ?? '—') ?></strong>
            </div>
            <div class="quick-punch-box">
                <span>Hours</span>
                <strong><?= htmlspecialchars($attendanceSummary['total_hours'] ?? '0:00') ?></strong>
            </div>
            <div class="quick-punch-box">
                <span>Status</span>
                <strong><?= htmlspecialchars($attendance['status'] ?? (hrIsWeeklyOffForEmployee($hrDb, $companyId, (int)$employee['id'], $today) ? 'Weekly off' : 'Not marked')) ?></strong>
            </div>
        </div>
    </div>
    <div class="quick-punch-actions">
        <form method="post" class="inline-form">
            <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">
            <button class="button" type="submit" name="action" value="checkin" <?= !empty($attendanceSummary['check_in']) ? 'disabled' : '' ?>><?= !empty($attendanceSummary['check_in']) ? 'Checked In' : 'Check In' ?></button>
        </form>
        <form method="post" class="inline-form">
            <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">
            <button class="button secondary" type="submit" name="action" value="checkout" <?= empty($attendanceSummary['check_in']) ? 'disabled' : '' ?>>Check Out</button>
        </form>
    </div>
</div>
<?php endif; ?>
<?php if ($employeeRole === 'EMPLOYEE'): ?>
<div class="page-head">
    <div>
        <h2>Hello, <?= htmlspecialchars($employee['first_name'].' '.$employee['last_name']) ?></h2>
        <p>Employee attendance and personal summary.</p>
    </div>
</div>

<div class="panel" style="margin-top:18px;">
    <h3>Today's attendance</h3>
    <div class="metric-list">
        <div><span>Check-in</span><strong><?= htmlspecialchars($attendanceSummary['check_in'] ?? 'Not yet') ?></strong></div>
        <div><span>Check-out</span><strong><?= htmlspecialchars($attendanceSummary['check_out'] ?? 'Not yet') ?></strong></div>
        <div><span>Hours</span><strong><?= htmlspecialchars($attendanceSummary['total_hours']) ?></strong></div>
        <div><span>Status</span><strong><?= htmlspecialchars($attendance['status'] ?? 'Not marked') ?></strong></div>
    </div>
</div>

<div class="stats">
    <article class="stat">
        <span class="label">Employee code</span>
        <strong><?= htmlspecialchars($employee['employee_code']) ?></strong>
        <small><?= htmlspecialchars($employee['department'] ?: 'No department') ?></small>
    </article>
    <article class="stat">
        <span class="label">Status</span>
        <strong><?= htmlspecialchars($employee['status']) ?></strong>
        <small><?= htmlspecialchars($employee['designation'] ?: 'No designation') ?></small>
    </article>
    <article class="stat">
        <span class="label">Today</span>
        <strong><?= htmlspecialchars($today) ?></strong>
        <small><?= htmlspecialchars($attendance['status'] ?? 'Not marked') ?></small>
    </article>
</div>
<?php else: ?>
<div class="page-head">
    <div>
        <h2>Executive workforce overview</h2>
        <p>Key attendance, leave, and workforce metrics at a glance.</p>
    </div>
</div>

<div class="panel" style="margin-top:18px;">
    <h3>Today's attendance</h3>
    <div class="metric-list">
        <div><span>Check-in</span><strong><?= htmlspecialchars($attendanceSummary['check_in'] ?? 'Not yet') ?></strong></div>
        <div><span>Check-out</span><strong><?= htmlspecialchars($attendanceSummary['check_out'] ?? 'Not yet') ?></strong></div>
        <div><span>Hours</span><strong><?= htmlspecialchars($attendanceSummary['total_hours']) ?></strong></div>
        <div><span>Status</span><strong><?= htmlspecialchars($attendance['status'] ?? 'Not marked') ?></strong></div>
    </div>
</div>

<div class="stats executive-stats">
    <article class="stat">
        <span class="label">Total active</span>
        <strong><?= $companyMetrics['totalEmployees'] ?></strong>
        <small>Employees</small>
    </article>
    <article class="stat">
        <span class="label">Present</span>
        <strong><?= $companyMetrics['presentToday'] ?></strong>
        <small>Today</small>
    </article>
    <article class="stat">
        <span class="label">Absent</span>
        <strong><?= $companyMetrics['absentToday'] ?></strong>
        <small>Today</small>
    </article>
    <article class="stat">
        <span class="label">Leave pending</span>
        <strong><?= $companyMetrics['pendingLeave'] ?></strong>
        <small>Approvals</small>
    </article>
</div>

<?php endif; ?>

<div class="grid two-col" style="margin-top:18px;">
    <article class="panel">
        <div class="analytics-topline">
            <div>
                <span class="eyebrow-small">Company calendar</span>
                <h3><?= htmlspecialchars($calendarLabel) ?></h3>
            </div>
            <div class="calendar-nav">
                <a href="employee_dashboard.php?calendar_month=<?= htmlspecialchars($calendarPrev) ?>" class="button secondary">Prev</a>
                <a href="employee_dashboard.php?calendar_month=<?= htmlspecialchars($calendarNext) ?>" class="button secondary">Next</a>
            </div>
        </div>
        <div class="holiday-calendar">
            <div class="calendar-weekdays">
                <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
            </div>
            <div class="calendar-grid">
                <?php foreach ($calendarDays as $item): ?>
                    <?php $isToday = ($item['date'] !== '' && $item['date'] === $today && $calendarMonth === date('Y-m')); ?>
                    <?php $dayAttendance = $item['attendance'] ?? ['check_in' => null, 'check_out' => null, 'total_hours' => '0:00']; ?>
                    <?php $isSelected = ($item['date'] !== '' && $item['date'] === $selectedDate); ?>
                    <a href="employee_dashboard.php?calendar_month=<?= urlencode($calendarMonth) ?>&selected_date=<?= urlencode($item['date']) ?>" class="calendar-cell <?= !empty($item['holidays']) ? 'has-holiday' : '' ?> <?= $item['weekly_off'] ? 'has-weekly-off' : '' ?> <?= $item['day'] === '' ? 'empty' : '' ?> <?= $isToday ? 'is-today' : '' ?> <?= $isSelected ? 'selected-day' : '' ?>" style="text-decoration:none;">
                        <?php if ($item['day'] !== ''): ?>
                            <span class="calendar-day"><?= htmlspecialchars($item['day']) ?></span>
                            <?php if ($item['weekly_off']): ?>
                                <span class="time-pill neutral-pill">Weekly off</span>
                                <span class="hours-pill">0:00 hrs</span>
                            <?php elseif (!empty($item['holidays'])): ?>
                                <?php foreach ($item['holidays'] as $holiday): ?>
                                    <span class="holiday-pill"><?= htmlspecialchars($holiday['holiday_name']) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (!empty($dayAttendance['check_in']) || !empty($dayAttendance['check_out'])): ?>
                                <span class="time-pill in-time">In: <?= htmlspecialchars($dayAttendance['check_in'] ?? '—') ?></span>
                                <span class="time-pill out-time">Out: <?= htmlspecialchars($dayAttendance['check_out'] ?? '—') ?></span>
                                <?php if (!empty($dayAttendance['total_hours'])): ?>
                                    <span class="hours-pill"><?= htmlspecialchars($dayAttendance['total_hours']) ?> hrs</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </article>

    <article class="panel">
        <h3>Attendance history for <?= htmlspecialchars($selectedDate) ?></h3>
        <div class="metric-list" style="margin-bottom:16px;">
            <div><span>Check-in</span><strong><?= htmlspecialchars($selectedDaySummary['check_in'] ?? 'Not yet') ?></strong></div>
            <div><span>Check-out</span><strong><?= htmlspecialchars($selectedDaySummary['check_out'] ?? 'Not yet') ?></strong></div>
            <div><span>Hours</span><strong><?= htmlspecialchars($selectedDaySummary['total_hours']) ?></strong></div>
            <div><span>Weekly off</span><strong><?= hrIsWeeklyOffForEmployee($hrDb, $companyId, (int)$employee['id'], $selectedDate) ? 'Yes' : 'No' ?></strong></div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Time</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($selectedDayLogs): ?>
                        <?php foreach ($selectedDayLogs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $log['punch_type']))) ?></td>
                                <td><?= htmlspecialchars($log['punch_time']) ?></td>
                                <td><?= htmlspecialchars($log['source']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="empty">No punch history recorded for this date.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="panel">
        <h3>Holiday list</h3>
        <div class="holiday-list">
            <?php if ($calendarHolidayRows): ?>
                <?php foreach ($calendarHolidayRows as $holiday): ?>
                    <div class="holiday-item">
                        <div class="holiday-date-block">
                            <strong><?= htmlspecialchars(date('d', strtotime($holiday['holiday_date']))) ?></strong>
                            <small><?= htmlspecialchars(date('M', strtotime($holiday['holiday_date']))) ?></small>
                        </div>
                        <div class="holiday-meta">
                            <strong><?= htmlspecialchars($holiday['holiday_name']) ?></strong>
                            <small><?= htmlspecialchars($holiday['state_name'] ?: 'All states') ?> · <?= htmlspecialchars($holiday['holiday_date']) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty">No holidays scheduled for this month.</div>
            <?php endif; ?>
        </div>
    </article>
</div>

<?php if ($employeeRole !== 'EMPLOYEE'): ?>
<div class="grid two-col" style="margin-top: 18px;">
    <article class="panel analytics-panel">
        <div class="analytics-topline">
            <div>
                <span class="eyebrow-small">Attendance trend</span>
                <h3>Monthly workforce activity</h3>
            </div>
            <span class="badge approved">+<?= round((($monthlyTrend[count($monthlyTrend)-1]['present'] - $monthlyTrend[0]['present']) / max(1, $monthlyTrend[0]['present'])) * 100) ?>%</span>
        </div>
        <svg class="trend-chart" viewBox="0 0 300 140" preserveAspectRatio="none" aria-label="Attendance trend chart">
            <polyline points="<?= htmlspecialchars($trendSvg) ?>" fill="none" stroke="#2563eb" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></polyline>
            <?php foreach ($monthlyTrend as $index => $item): ?>
                <?php $x = 20 + ($index * 58); $y = 120 - (($item['present'] / $trendMax) * 80); ?>
                <circle cx="<?= $x ?>" cy="<?= $y ?>" r="4.5" fill="#ef4444"></circle>
            <?php endforeach; ?>
        </svg>
        <div class="chart-labels">
            <?php foreach ($monthlyTrend as $item): ?>
                <span><?= htmlspecialchars($item['label']) ?></span>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="panel analytics-panel">
        <div class="analytics-topline">
            <div>
                <span class="eyebrow-small">People cost</span>
                <h3>Payroll load</h3>
            </div>
            <span class="badge neutral">Active staff</span>
        </div>
        <div class="cost-box">
            <strong><?= htmlspecialchars('₹ ' . number_format($monthlyCost, 0)) ?></strong>
            <small>Monthly employee cost</small>
        </div>
        <div class="metric-list compact-list">
            <div><span>Avg cost / employee</span><strong><?= htmlspecialchars('₹ ' . number_format($monthlyCost / max(1, $companyMetrics['totalEmployees']), 0)) ?></strong></div>
            <div><span>Active employees</span><strong><?= $companyMetrics['totalEmployees'] ?></strong></div>
            <div><span>Pending approvals</span><strong><?= $companyMetrics['pendingLeave'] ?></strong></div>
        </div>
    </article>
</div>

<div class="grid two-col" style="margin-top: 18px;">
    <article class="panel">
        <h3>Key links</h3>
        <div class="metric-list">
            <div><span>Employee directory</span><a href="employees.php">Open</a></div>
            <div><span>Onboarding</span><a href="employee_onboarding.php">Open</a></div>
            <div><span>Joining setup</span><a href="employee_joining.php">Open</a></div>
            <div><span>Payroll setup</span><a href="employee_payroll.php">Open</a></div>
            <div><span>IT assets</span><a href="employee_assets.php">Open</a></div>
        </div>
    </article>
    <article class="panel">
        <h3>Today</h3>
        <div class="metric-list">
            <div><span>Date</span><strong><?= htmlspecialchars($today) ?></strong></div>
            <div><span>Late today</span><strong><?= $companyMetrics['lateToday'] ?></strong></div>
            <div><span>Unlogged</span><strong><?= $companyMetrics['unloggedToday'] ?></strong></div>
            <div><span>Approved leave</span><strong><?= $companyMetrics['leaveApprovedThisMonth'] ?></strong></div>
        </div>
    </article>
</div>
<?php endif; ?>

<?php if ($canMarkAttendance): ?>
<div class="grid">
    <article class="panel">
        <h3><?= $employeeRole === 'HR' ? 'HR attendance actions' : 'Attendance actions' ?></h3>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">
            <div class="form-actions" style="display:flex;gap:10px;flex-wrap:wrap;">
                <button class="button" type="submit" name="action" value="checkin">Check In</button>
                <button class="button secondary" type="submit" name="action" value="checkout">Check Out</button>
            </div>
        </form>
        <p class="panel-sub">Check-in and check-out are stored against today’s attendance record.</p>
    </article>
</div>
<?php endif; ?>

<?php if ($canManageAssets): ?>
<div class="panel executive-panel" style="margin-top: 24px;">
    <div class="executive-header">
        <div>
            <h3>IT asset allocation</h3>
            <p class="panel-sub">Assign company equipment to employees and maintain asset records.</p>
        </div>
    </div>
    <div class="grid two-col">
        <article class="panel sub-panel">
            <h4>Allocate asset</h4>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">
                <input type="hidden" name="action" value="assign_asset">
                <div class="form-grid">
                    <label>Employee<select name="employee_id">
                        <?php foreach ($allEmployees as $person): ?>
                            <option value="<?= (int)$person['id'] ?>"><?= htmlspecialchars($person['first_name'].' '.$person['last_name']) ?> (<?= htmlspecialchars($person['employee_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select></label>
                    <label>Asset type<select name="asset_type"><option>Laptop</option><option>Desktop</option><option>Mobile</option><option>Monitor</option><option>Other</option></select></label>
                    <label>Asset name<input name="asset_name" required></label>
                    <label>Serial number<input name="serial_number"></label>
                </div>
                <div class="form-actions" style="margin-top:12px;"><button class="button" type="submit">Allocate asset</button></div>
            </form>
        </article>
        <article class="panel sub-panel">
            <h4>Assigned assets</h4>
            <?php if ($assetAssignments): ?>
                <ul class="list-stack">
                    <?php foreach (array_slice($assetAssignments, 0, 6) as $asset): ?>
                        <li>
                            <span><strong><?= htmlspecialchars($asset['asset_name']) ?></strong><small><?= htmlspecialchars($asset['first_name'].' '.$asset['last_name']) ?> · <?= htmlspecialchars($asset['serial_number'] ?: 'No serial') ?></small></span>
                            <span class="badge approved"><?= htmlspecialchars($asset['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="panel-sub">No assets allocated yet.</p>
            <?php endif; ?>
        </article>
    </div>
</div>
<?php endif; ?>

<?php if ($canViewExecutive): ?>
<div class="panel executive-panel" style="margin-top: 24px;">
    <div class="executive-header">
        <div>
            <h3><?= $employeeRole === 'MD' ? 'MD attendance overview' : 'HR attendance overview' ?></h3>
            <p class="panel-sub">Company-wide workforce visibility for <?= htmlspecialchars(date('d M Y')) ?></p>
        </div>
        <span class="badge primary"><?= htmlspecialchars($employeeRole) ?> view</span>
    </div>

    <div class="panel sub-panel" style="margin-top:18px; margin-bottom:18px;">
        <h4>Employee roster & actions</h4>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allEmployees as $person): ?>
                        <tr>
                            <td>
                                <b><?= htmlspecialchars($person['first_name'].' '.$person['last_name']) ?></b><br>
                                <small><?= htmlspecialchars($person['employee_code']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($person['role'] ?? 'Employee') ?></td>
                            <td><?= htmlspecialchars($person['department'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($person['designation'] ?: '—') ?></td>
                            <td><span class="badge <?= strtolower((string)($person['status'] ?? 'Active')) == 'exited' ? 'neutral' : 'approved' ?>"><?= htmlspecialchars($person['status'] ?? 'Active') ?></span></td>
                            <td>
                                <?php if ((string)($person['status'] ?? 'Active') === 'Exited'): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">
                                        <input type="hidden" name="action" value="undo_termination">
                                        <input type="hidden" name="employee_id" value="<?= (int)$person['id'] ?>">
                                        <button class="button" type="submit" <?= ((int)$person['id'] === (int)$employee['id']) ? 'disabled' : '' ?>>Undo</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">
                                        <input type="hidden" name="action" value="terminate_employee">
                                        <input type="hidden" name="employee_id" value="<?= (int)$person['id'] ?>">
                                        <button class="button secondary" type="submit" <?= ((int)$person['id'] === (int)$employee['id']) ? 'disabled' : '' ?>>Terminate</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid two-col">
        <article class="panel sub-panel">
            <h4>Absent employees</h4>
            <?php if ($absentList): ?>
            <ul class="list-stack">
                <?php foreach ($absentList as $person): ?>
                <li>
                    <span><strong><?= htmlspecialchars($person['first_name'].' '.$person['last_name']) ?></strong><small><?= htmlspecialchars($person['employee_code']) ?> · <?= htmlspecialchars($person['department'] ?: 'No dept') ?></small></span>
                    <span class="badge absent"><?= htmlspecialchars($person['status']) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="panel-sub">No employees are absent today.</p>
            <?php endif; ?>
        </article>

        <article class="panel sub-panel">
            <h4>Still not logged in</h4>
            <?php if ($unloggedList): ?>
            <ul class="list-stack">
                <?php foreach ($unloggedList as $person): ?>
                <li>
                    <span><strong><?= htmlspecialchars($person['first_name'].' '.$person['last_name']) ?></strong><small><?= htmlspecialchars($person['employee_code']) ?> · <?= htmlspecialchars($person['department'] ?: 'No dept') ?></small></span>
                    <span class="badge neutral">Pending</span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="panel-sub">All employees have checked in.</p>
            <?php endif; ?>
        </article>
    </div>

    <div class="grid two-col" style="margin-top: 16px;">
        <article class="panel sub-panel">
            <h4>Leave today</h4>
            <?php if ($leaveList): ?>
            <ul class="list-stack">
                <?php foreach ($leaveList as $item): ?>
                <li>
                    <span><strong><?= htmlspecialchars($item['first_name'].' '.$item['last_name']) ?></strong><small><?= htmlspecialchars($item['leave_type']) ?> · <?= htmlspecialchars($item['start_date']) ?> to <?= htmlspecialchars($item['end_date']) ?></small></span>
                    <span class="badge approved"><?= htmlspecialchars($item['status']) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="panel-sub">No employees are on leave today.</p>
            <?php endif; ?>
        </article>

        <article class="panel sub-panel">
            <h4>Company alerts</h4>
            <ul class="list-stack compact">
                <li><span><strong>Late today</strong><small>Attendance exceptions</small></span><span><?= $companyMetrics['lateToday'] ?></span></li>
                <li><span><strong>Pending approvals</strong><small>Leave requests</small></span><span><?= $companyMetrics['pendingLeave'] ?></span></li>
                <li><span><strong>Leave this month</strong><small>Approved requests</small></span><span><?= $companyMetrics['leaveApprovedThisMonth'] ?></span></li>
            </ul>
        </article>
    </div>
</div>
<?php endif; ?>
<?php hrPageEnd(); ?>
