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
$payslips = $hrDb->query("SELECT p.id, p.paid_days, p.gross, p.total_deductions, p.net_pay, r.payroll_month FROM hr_payslips p JOIN hr_payroll_runs r ON r.id = p.payroll_run_id JOIN hr_employees e ON e.id = p.employee_id WHERE e.company_id = $companyId AND p.employee_id = $employeeId ORDER BY r.payroll_month DESC")->fetch_all(MYSQLI_ASSOC);
hrPageStart('My payslips');
?>
<div class="page-head"><div><h2>My payslips</h2><p>View and print your generated monthly salary statements.</p></div></div>
<div class="panel">
    <div class="table-wrap"><table class="data-table"><thead><tr><th>MONTH</th><th>PAID DAYS</th><th>GROSS</th><th>DEDUCTIONS</th><th>NET PAY</th><th>ACTION</th></tr></thead><tbody>
    <?php if (!$payslips): ?><tr><td colspan="6" class="empty">No payslips have been generated yet.</td></tr><?php endif; ?>
    <?php foreach ($payslips as $payslip): ?><tr><td><b><?= htmlspecialchars(date('F Y', strtotime($payslip['payroll_month'] . '-01'))) ?></b></td><td><?= htmlspecialchars((string)$payslip['paid_days']) ?></td><td><?= hrMoney((float)$payslip['gross']) ?></td><td><?= hrMoney((float)$payslip['total_deductions']) ?></td><td><b><?= hrMoney((float)$payslip['net_pay']) ?></b></td><td><a class="button secondary" href="payslip.php?id=<?= (int)$payslip['id'] ?>">View / Print</a></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php hrPageEnd(); ?>