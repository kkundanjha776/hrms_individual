<?php
require_once __DIR__ . '/core.php';
hrRequireEmployeeLogin();
$employee = hrCurrentEmployee($hrDb);
if (!$employee) {
    header('Location: employee_login.php');
    exit;
}
$companyId = (int)$employee['company_id'];
$employeeId = (int)$employee['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = trim((string)($_POST['leave_type'] ?? ''));
    $start = (string)($_POST['start_date'] ?? '');
    $end = (string)($_POST['end_date'] ?? '');
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($type === '' || $start === '' || $end === '' || $end < $start) {
        hrFlash('Choose a leave type and valid dates.', 'error');
    } else {
        $stmt = $hrDb->prepare('INSERT INTO hr_leave_requests (company_id, employee_id, leave_type, start_date, end_date, reason) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iissss', $companyId, $employeeId, $type, $start, $end, $reason);
        $stmt->execute();
        $stmt->close();
        hrFlash('Leave request submitted for approval.');
    }
    header('Location: employee_leave.php');
    exit;
}
$requests = $hrDb->query("SELECT leave_type, start_date, end_date, reason, status, created_at FROM hr_leave_requests WHERE company_id=$companyId AND employee_id=$employeeId ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
hrPageStart('My leave');
?>
<div class="page-head"><div><h2>My leave</h2><p>Submit time off and track approval status.</p></div></div>
<div class="grid two-col">
    <article class="form-card"><h3>Request leave</h3><form method="post"><input type="hidden" name="csrf" value="<?= hrCsrf() ?>"><div class="form-grid"><label>Leave type<select name="leave_type" required><option value="">Choose type</option><option>Annual Leave</option><option>Sick Leave</option><option>Personal Leave</option><option>Unpaid Leave</option></select></label><label>Start date<input type="date" name="start_date" required></label><label>End date<input type="date" name="end_date" required></label><label class="span-all">Reason<textarea name="reason" rows="4" placeholder="Add a note for HR"></textarea></label></div><div class="form-actions"><button class="button">Submit request</button></div></form></article>
    <article class="panel"><h3>Request history</h3><div class="leave-history"><?php if (!$requests): ?><div class="empty">No leave requests yet.</div><?php endif; ?><?php foreach ($requests as $request): ?><div class="leave-history-item"><div><strong><?= htmlspecialchars($request['leave_type']) ?></strong><small><?= htmlspecialchars($request['start_date']) ?> to <?= htmlspecialchars($request['end_date']) ?></small><?php if ($request['reason'] !== ''): ?><small><?= htmlspecialchars($request['reason']) ?></small><?php endif; ?></div><span class="badge <?= strtolower($request['status']) ?>"><?= htmlspecialchars($request['status']) ?></span></div><?php endforeach; ?></div></article>
</div>
<?php hrPageEnd(); ?>