<?php
require_once __DIR__ . '/core.php';
hrRequireEmployeeLogin();
$currentEmployee = hrCurrentEmployee($hrDb);
if (!$currentEmployee || strtoupper((string)($currentEmployee['role'] ?? '')) !== 'IT') {
    header('Location: employee_dashboard.php');
    exit;
}
hrRequireCsrf();

$adminId = (int)$currentEmployee['id'];
$adminCompanyId = hrCompanyId($hrDb);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'transfer_employee') {
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $destinationCompanyId = (int)($_POST['destination_company_id'] ?? 0);
    if ($employeeId <= 0 || $destinationCompanyId <= 0 || $employeeId === $adminId) {
        hrFlash('Choose a valid employee and destination company. Your administrator account cannot be transferred.', 'error');
    } else {
        $check = $hrDb->prepare('SELECT id FROM hr_companies WHERE id = ? AND status = "Active" LIMIT 1');
        $check->bind_param('i', $destinationCompanyId);
        $check->execute();
        $validCompany = (bool)$check->get_result()->fetch_assoc();
        $check->close();
        if (!$validCompany) {
            hrFlash('Destination company is not active.', 'error');
        } else {
            $sourceCompanyId = $adminCompanyId;
            $employeeCheck = $hrDb->prepare('SELECT employee_code FROM hr_employees WHERE id = ? AND company_id = ? LIMIT 1');
            $employeeCheck->bind_param('ii', $employeeId, $sourceCompanyId);
            $employeeCheck->execute();
            $sourceEmployee = $employeeCheck->get_result()->fetch_assoc();
            $employeeCheck->close();
            if (!$sourceEmployee) {
                hrFlash('Employee was not found in your company.', 'error');
            } else {
                $duplicateCheck = $hrDb->prepare('SELECT id FROM hr_employees WHERE company_id = ? AND employee_code = ? LIMIT 1');
                $duplicateCheck->bind_param('is', $destinationCompanyId, $sourceEmployee['employee_code']);
                $duplicateCheck->execute();
                $duplicateExists = (bool)$duplicateCheck->get_result()->fetch_assoc();
                $duplicateCheck->close();
                $destinationCode = (string)$sourceEmployee['employee_code'];
                if ($duplicateExists) {
                    $baseCode = $destinationCode;
                    $suffix = 2;
                    do {
                        $destinationCode = $baseCode . '-' . $suffix;
                        $codeCheck = $hrDb->prepare('SELECT id FROM hr_employees WHERE company_id = ? AND employee_code = ? LIMIT 1');
                        $codeCheck->bind_param('is', $destinationCompanyId, $destinationCode);
                        $codeCheck->execute();
                        $codeTaken = (bool)$codeCheck->get_result()->fetch_assoc();
                        $codeCheck->close();
                        $suffix++;
                    } while ($codeTaken);
                }
                {
                    try {
                        $stmt = $hrDb->prepare('UPDATE hr_employees SET company_id = ?, employee_code = ? WHERE id = ? AND company_id = ?');
                        $stmt->bind_param('isii', $destinationCompanyId, $destinationCode, $employeeId, $sourceCompanyId);
                        $stmt->execute();
                        $changed = $stmt->affected_rows;
                        $stmt->close();
                        $message = $changed ? 'Employee transferred to the selected company with code ' . $destinationCode . '.' : 'Employee was not found in your company.';
                        hrFlash($message, $changed ? 'success' : 'error');
                    } catch (mysqli_sql_exception $exception) {
                        hrFlash('Transfer could not be completed because the employee code already exists in the destination company.', 'error');
                    }
                }
            }
        }
    }
    header('Location: admin.php');
    exit;
}

$companies = hrCompanies($hrDb);
$employees = $hrDb->query("SELECT e.id, e.employee_code, e.first_name, e.last_name, e.role, e.status, e.company_id, c.company_name FROM hr_employees e JOIN hr_companies c ON c.id = e.company_id WHERE e.company_id = $adminCompanyId ORDER BY e.first_name, e.last_name")->fetch_all(MYSQLI_ASSOC);
hrPageStart('Company administrator');
?>
<div class="page-head"><div><h2>Company administrator</h2><p>Manage employees in your company and transfer them to another active company.</p></div></div>
<div class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>EMPLOYEE</th><th>ROLE</th><th>CURRENT COMPANY</th><th>TRANSFER TO</th><th>ACTION</th></tr></thead><tbody>
<?php foreach ($employees as $employee): ?>
<tr>
    <td><b><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></b><br><small><?= htmlspecialchars($employee['employee_code']) ?></small></td>
    <td><?= htmlspecialchars($employee['role']) ?></td>
    <td><?= htmlspecialchars($employee['company_name']) ?></td>
    <?php if ((int)$employee['id'] !== $adminId): ?>
        <td><form method="post" class="form-actions"><input type="hidden" name="csrf" value="<?= hrCsrf() ?>"><input type="hidden" name="action" value="transfer_employee"><input type="hidden" name="employee_id" value="<?= (int)$employee['id'] ?>"><select name="destination_company_id"><option value="">Select company</option><?php foreach ($companies as $company): ?><option value="<?= (int)$company['id'] ?>" <?= (int)$company['id'] === (int)$employee['company_id'] ? 'disabled' : '' ?>><?= htmlspecialchars($company['company_name']) ?></option><?php endforeach; ?></select></td>
        <td><button class="button secondary" type="submit">Transfer</button></form></td>
    <?php else: ?>
        <td><span class="badge primary">Your account</span></td><td>-</td>
    <?php endif; ?>
</tr>
<?php endforeach; ?>
<?php if (!$employees): ?><tr><td colspan="5" class="empty">No employees found.</td></tr><?php endif; ?></tbody></table></div></div>
<?php hrPageEnd(); ?>
