<?php
require_once __DIR__ . '/core.php';
hrRequireEmployeeLogin();
$currentEmployee = hrCurrentEmployee($hrDb);
if (!$currentEmployee || !in_array(strtoupper((string)($currentEmployee['role'] ?? 'Employee')), ['HR', 'MD'], true)) {
    header('Location: employee_dashboard.php');
    exit;
}

$companyId = hrCompanyId($hrDb);
$selectedEmployeeId = (int)($_REQUEST['employee_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hrRequireCsrf();
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    if ($employeeId > 0) {
        $joinDate = $_POST['join_date'] ?? date('Y-m-d');
        $department = trim((string)($_POST['department'] ?? ''));
        $designation = trim((string)($_POST['designation'] ?? ''));
        $employmentType = trim((string)($_POST['employment_type'] ?? 'Full time'));
        $role = strtoupper(trim((string)($_POST['role'] ?? 'Employee')));

        $hrDb->query("UPDATE hr_employees SET department='" . $hrDb->real_escape_string($department) . "', designation='" . $hrDb->real_escape_string($designation) . "', join_date='" . $hrDb->real_escape_string($joinDate) . "', employment_type='" . $hrDb->real_escape_string($employmentType) . "', role='" . $hrDb->real_escape_string($role) . "' WHERE id=$employeeId AND company_id=$companyId");

        $probation = trim((string)($_POST['probation_period'] ?? '6 months'));
        $offerStatus = trim((string)($_POST['offer_status'] ?? 'Pending'));
        $backgroundCheck = trim((string)($_POST['background_check'] ?? 'Pending'));
        $assetDeclaration = trim((string)($_POST['asset_declaration'] ?? 'Pending'));
        $documentsUploaded = trim((string)($_POST['documents_uploaded'] ?? 'No'));
        $notes = trim((string)($_POST['notes'] ?? ''));

        $hrDb->query("INSERT INTO hr_employee_onboarding (company_id, employee_id, joining_date, probation_period, offer_status, background_check, asset_declaration, documents_uploaded, notes) VALUES ($companyId, $employeeId, '" . $hrDb->real_escape_string($joinDate) . "', '" . $hrDb->real_escape_string($probation) . "', '" . $hrDb->real_escape_string($offerStatus) . "', '" . $hrDb->real_escape_string($backgroundCheck) . "', '" . $hrDb->real_escape_string($assetDeclaration) . "', '" . $hrDb->real_escape_string($documentsUploaded) . "', '" . $hrDb->real_escape_string($notes) . "') ON DUPLICATE KEY UPDATE joining_date=VALUES(joining_date), probation_period=VALUES(probation_period), offer_status=VALUES(offer_status), background_check=VALUES(background_check), asset_declaration=VALUES(asset_declaration), documents_uploaded=VALUES(documents_uploaded), notes=VALUES(notes)");

        hrFlash('Joining details saved successfully.');
        header('Location: employee_joining.php?employee_id=' . $employeeId);
        exit;
    }

    hrFlash('Please select an employee to update.', 'error');
    header('Location: employee_joining.php');
    exit;
}

$employees = $hrDb->query("SELECT e.*, o.* FROM hr_employees e LEFT JOIN hr_employee_onboarding o ON o.employee_id = e.id WHERE e.company_id=$companyId ORDER BY e.first_name, e.last_name")->fetch_all(MYSQLI_ASSOC);
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

hrPageStart('Employee joining');
?>
<div class="page-head">
    <div>
        <h2>Joining setup</h2>
        <p>Update role, department, designation, and onboarding status for each employee.</p>
    </div>
    <a class="button secondary" href="employee_onboarding.php">New onboarding</a>
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
                    <label>Employee code<input value="<?= htmlspecialchars($selectedEmployee['employee_code']) ?>" readonly></label>
                    <label>Role<select name="role"><option value="Employee" <?= strtoupper((string)($selectedEmployee['role'] ?? 'Employee')) === 'EMPLOYEE' ? 'selected' : '' ?>>Employee</option><option value="HR" <?= strtoupper((string)($selectedEmployee['role'] ?? 'Employee')) === 'HR' ? 'selected' : '' ?>>HR</option><option value="IT" <?= strtoupper((string)($selectedEmployee['role'] ?? 'Employee')) === 'IT' ? 'selected' : '' ?>>IT</option><option value="MD" <?= strtoupper((string)($selectedEmployee['role'] ?? 'Employee')) === 'MD' ? 'selected' : '' ?>>MD</option></select></label>
                    <label>Joining date<input type="date" name="join_date" value="<?= htmlspecialchars($selectedEmployee['join_date'] ?: date('Y-m-d')) ?>"></label>
                    <label>Department<input name="department" value="<?= htmlspecialchars($selectedEmployee['department']) ?>"></label>
                    <label>Designation<input name="designation" value="<?= htmlspecialchars($selectedEmployee['designation']) ?>"></label>
                    <label>Employment type<select name="employment_type"><option <?= ($selectedEmployee['employment_type'] ?? 'Full time') === 'Full time' ? 'selected' : '' ?>>Full time</option><option <?= ($selectedEmployee['employment_type'] ?? 'Full time') === 'Contract' ? 'selected' : '' ?>>Contract</option><option <?= ($selectedEmployee['employment_type'] ?? 'Full time') === 'Intern' ? 'selected' : '' ?>>Intern</option></select></label>
                    <label>Probation period<input name="probation_period" value="<?= htmlspecialchars($selectedEmployee['probation_period'] ?? '6 months') ?>"></label>
                    <label>Offer status<select name="offer_status"><option <?= ($selectedEmployee['offer_status'] ?? 'Pending') === 'Pending' ? 'selected' : '' ?>>Pending</option><option <?= ($selectedEmployee['offer_status'] ?? 'Pending') === 'Sent' ? 'selected' : '' ?>>Sent</option><option <?= ($selectedEmployee['offer_status'] ?? 'Pending') === 'Accepted' ? 'selected' : '' ?>>Accepted</option><option <?= ($selectedEmployee['offer_status'] ?? 'Pending') === 'Rejected' ? 'selected' : '' ?>>Rejected</option></select></label>
                    <label>Background check<select name="background_check"><option <?= ($selectedEmployee['background_check'] ?? 'Pending') === 'Pending' ? 'selected' : '' ?>>Pending</option><option <?= ($selectedEmployee['background_check'] ?? 'Pending') === 'Passed' ? 'selected' : '' ?>>Passed</option><option <?= ($selectedEmployee['background_check'] ?? 'Pending') === 'Failed' ? 'selected' : '' ?>>Failed</option></select></label>
                    <label>Asset declaration<select name="asset_declaration"><option <?= ($selectedEmployee['asset_declaration'] ?? 'Pending') === 'Pending' ? 'selected' : '' ?>>Pending</option><option <?= ($selectedEmployee['asset_declaration'] ?? 'Pending') === 'Completed' ? 'selected' : '' ?>>Completed</option></select></label>
                    <label>Documents uploaded<select name="documents_uploaded"><option <?= ($selectedEmployee['documents_uploaded'] ?? 'No') === 'No' ? 'selected' : '' ?>>No</option><option <?= ($selectedEmployee['documents_uploaded'] ?? 'No') === 'Yes' ? 'selected' : '' ?>>Yes</option></select></label>
                    <label class="full">Notes<textarea name="notes" rows="3"><?= htmlspecialchars($selectedEmployee['notes'] ?? '') ?></textarea></label>
                </div>
                <div class="form-actions" style="margin-top:18px;">
                    <button class="button" type="submit">Save joining setup</button>
                </div>
            </form>
        <?php endif; ?>
    </article>

    <article class="panel">
        <h3>Onboarding status</h3>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Employee</th><th>Offer</th><th>Documents</th><th>Asset</th></tr></thead>
                <tbody>
                    <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></b><br><small><?= htmlspecialchars($employee['employee_code']) ?></small></td>
                            <td><?= htmlspecialchars($employee['offer_status'] ?? 'Pending') ?></td>
                            <td><?= htmlspecialchars($employee['documents_uploaded'] ?? 'No') ?></td>
                            <td><?= htmlspecialchars($employee['asset_declaration'] ?? 'Pending') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</div>
<?php hrPageEnd(); ?>
