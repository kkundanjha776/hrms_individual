<?php
require_once __DIR__ . '/core.php';
hrRequireEmployeeLogin();
$currentEmployee = hrCurrentEmployee($hrDb);
if (!$currentEmployee || !in_array(strtoupper((string)($currentEmployee['role'] ?? 'Employee')), ['HR', 'MD'], true)) {
    header('Location: employee_dashboard.php');
    exit;
}

$companyId = hrCompanyId($hrDb);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hrRequireCsrf();
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    if ($employeeId > 0) {
        $basic = (float)($_POST['basic_salary'] ?? 0);
        $hra = (float)($_POST['hra'] ?? 0);
        $allowance = (float)($_POST['allowance'] ?? 0);
        $pfRate = (float)($_POST['pf_rate'] ?? 12);
        $esiRate = (float)($_POST['esi_rate'] ?? 0.75);
        $tax = (float)($_POST['professional_tax'] ?? 0);
        $payCycle = trim((string)($_POST['pay_cycle'] ?? 'Monthly'));
        $effectiveDate = trim((string)($_POST['effective_date'] ?? date('Y-m-d')));
        $bankName = trim((string)($_POST['bank_name'] ?? ''));
        $bankAccount = trim((string)($_POST['bank_account'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        $hrDb->query("UPDATE hr_employees SET basic_salary=" . $basic . ", hra=" . $hra . ", allowance=" . $allowance . ", pf_rate=" . $pfRate . ", esi_rate=" . $esiRate . ", professional_tax=" . $tax . ", bank_name='" . $hrDb->real_escape_string($bankName) . "', bank_account='" . $hrDb->real_escape_string($bankAccount) . "' WHERE id=$employeeId AND company_id=$companyId");

        $gross = $basic + $hra + $allowance;
        $net = $gross - ($gross * ($pfRate / 100)) - ($gross * ($esiRate / 100)) - $tax;

        $hrDb->query("INSERT INTO hr_employee_payroll_setup (company_id, employee_id, pay_cycle, gross_salary, net_salary, effective_date, bank_name, bank_account, notes) VALUES ($companyId, $employeeId, '" . $hrDb->real_escape_string($payCycle) . "', " . $gross . ", " . $net . ", '" . $hrDb->real_escape_string($effectiveDate) . "', '" . $hrDb->real_escape_string($bankName) . "', '" . $hrDb->real_escape_string($bankAccount) . "', '" . $hrDb->real_escape_string($notes) . "') ON DUPLICATE KEY UPDATE pay_cycle=VALUES(pay_cycle), gross_salary=VALUES(gross_salary), net_salary=VALUES(net_salary), effective_date=VALUES(effective_date), bank_name=VALUES(bank_name), bank_account=VALUES(bank_account), notes=VALUES(notes)");

        hrFlash('Payroll setup saved successfully.');
        header('Location: employee_payroll.php?employee_id=' . $employeeId);
        exit;
    }

    hrFlash('Please select an employee to configure payroll.', 'error');
    header('Location: employee_payroll.php');
    exit;
}

$selectedEmployeeId = (int)($_REQUEST['employee_id'] ?? 0);
$employees = $hrDb->query("SELECT e.*, p.* FROM hr_employees e LEFT JOIN hr_employee_payroll_setup p ON p.employee_id = e.id WHERE e.company_id=$companyId ORDER BY e.first_name, e.last_name")->fetch_all(MYSQLI_ASSOC);
$selectedEmployee = null;
foreach ($employees as $employee) {
    if ((int)$employee['id'] === $selectedEmployeeId) {
        $selectedEmployee = $employee;
        break;
    }
}
if ($selectedEmployee === null && !empty($employees)) {
    $selectedEmployee = $employees[0];
}

hrPageStart('Payroll setup');
?>
<div class="page-head">
    <div>
        <h2>Payroll setup</h2>
        <p>Configure monthly salary components and statutory values separately from onboarding.</p>
    </div>
    <a class="button secondary" href="payroll.php">Monthly payroll</a>
</div>

<div class="grid two-col">
    <article class="panel">
        <h3>Select employee</h3>
        <form method="get">
            <div class="form-grid">
                <label>Employee
                    <select name="employee_id" onchange="this.form.submit()">
                        <option value="">Choose employee</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?= (int)$employee['id'] ?>" <?= ($selectedEmployee && (int)$employee['id'] === (int)$selectedEmployee['id']) ? 'selected' : '' ?>><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_code'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </form>

        <?php if ($selectedEmployee): ?>
            <form method="post" style="margin-top:18px;">
                <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">
                <input type="hidden" name="employee_id" value="<?= (int)$selectedEmployee['id'] ?>">
                <div class="form-grid">
                    <label>Basic salary<input type="number" step="0.01" name="basic_salary" value="<?= htmlspecialchars((string)($selectedEmployee['basic_salary'] ?? 0)) ?>"></label>
                    <label>HRA<input type="number" step="0.01" name="hra" value="<?= htmlspecialchars((string)($selectedEmployee['hra'] ?? 0)) ?>"></label>
                    <label>Allowance<input type="number" step="0.01" name="allowance" value="<?= htmlspecialchars((string)($selectedEmployee['allowance'] ?? 0)) ?>"></label>
                    <label>PF rate <input type="number" step="0.01" name="pf_rate" value="<?= htmlspecialchars((string)($selectedEmployee['pf_rate'] ?? 12)) ?>"></label>
                    <label>ESI rate <input type="number" step="0.01" name="esi_rate" value="<?= htmlspecialchars((string)($selectedEmployee['esi_rate'] ?? 0.75)) ?>"></label>
                    <label>Professional tax<input type="number" step="0.01" name="professional_tax" value="<?= htmlspecialchars((string)($selectedEmployee['professional_tax'] ?? 0)) ?>"></label>
                    <label>Pay cycle<select name="pay_cycle"><option <?= ($selectedEmployee['pay_cycle'] ?? 'Monthly') === 'Monthly' ? 'selected' : '' ?>>Monthly</option><option <?= ($selectedEmployee['pay_cycle'] ?? 'Monthly') === 'Bi-weekly' ? 'selected' : '' ?>>Bi-weekly</option><option <?= ($selectedEmployee['pay_cycle'] ?? 'Monthly') === 'Weekly' ? 'selected' : '' ?>>Weekly</option></select></label>
                    <label>Effective date<input type="date" name="effective_date" value="<?= htmlspecialchars($selectedEmployee['effective_date'] ?? date('Y-m-d')) ?>"></label>
                    <label>Bank name<input name="bank_name" value="<?= htmlspecialchars($selectedEmployee['bank_name'] ?? '') ?>"></label>
                    <label>Bank account<input name="bank_account" value="<?= htmlspecialchars($selectedEmployee['bank_account'] ?? '') ?>"></label>
                    <label class="full">Notes<textarea name="notes" rows="3"><?= htmlspecialchars($selectedEmployee['notes'] ?? '') ?></textarea></label>
                </div>
                <div class="form-actions" style="margin-top:18px;">
                    <button class="button" type="submit">Save payroll setup</button>
                </div>
            </form>
        <?php endif; ?>
    </article>

    <article class="panel">
        <h3>Payroll summary</h3>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Employee</th><th>Gross</th><th>Net</th><th>Cycle</th></tr></thead>
                <tbody>
                    <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></b><br><small><?= htmlspecialchars($employee['employee_code']) ?></small></td>
                            <td><?= hrMoney((float)($employee['basic_salary'] ?? 0) + (float)($employee['hra'] ?? 0) + (float)($employee['allowance'] ?? 0)) ?></td>
                            <td><?= hrMoney((float)($employee['gross_salary'] ?? 0)) ?></td>
                            <td><?= htmlspecialchars($employee['pay_cycle'] ?? 'Monthly') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</div>
<?php hrPageEnd(); ?>
