<?php
require_once __DIR__ . '/core.php';
hrRequireEmployeeLogin();
$currentEmployee = hrCurrentEmployee($hrDb);
if (!$currentEmployee || !in_array(strtoupper((string)($currentEmployee['role'] ?? 'Employee')), ['HR', 'MD', 'IT'], true)) {
    header('Location: employee_dashboard.php');
    exit;
}

$companyId = hrCompanyId($hrDb);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hrRequireCsrf();
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $assetType = trim((string)($_POST['asset_type'] ?? ''));
    $assetName = trim((string)($_POST['asset_name'] ?? ''));
    $serialNumber = trim((string)($_POST['serial_number'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($employeeId > 0 && $assetName !== '') {
        $assignedDate = date('Y-m-d');
        $hrDb->query("INSERT INTO hr_employee_assets (company_id, employee_id, asset_type, asset_name, serial_number, assigned_date, status, issued_by, notes) VALUES ($companyId, $employeeId, '" . $hrDb->real_escape_string($assetType) . "', '" . $hrDb->real_escape_string($assetName) . "', '" . $hrDb->real_escape_string($serialNumber) . "', '" . $hrDb->real_escape_string($assignedDate) . "', 'Assigned', '" . $hrDb->real_escape_string(($currentEmployee['first_name'] ?? 'HR') . ' ' . ($currentEmployee['last_name'] ?? 'Manager')) . "', '" . $hrDb->real_escape_string($notes) . "')");
        hrFlash('IT asset assigned successfully.');
    } else {
        hrFlash('Asset name and employee are required.', 'error');
    }

    header('Location: employee_assets.php');
    exit;
}

$employees = $hrDb->query("SELECT * FROM hr_employees WHERE company_id=$companyId ORDER BY first_name, last_name")->fetch_all(MYSQLI_ASSOC);
$assets = $hrDb->query("SELECT a.*, e.first_name, e.last_name, e.employee_code FROM hr_employee_assets a JOIN hr_employees e ON e.id = a.employee_id WHERE a.company_id=$companyId ORDER BY a.assigned_date DESC")->fetch_all(MYSQLI_ASSOC);

hrPageStart('IT asset allocation');
?>
<div class="page-head">
    <div>
        <h2>IT asset allocation</h2>
        <p>Assign laptops, phones, and other company assets to employees from a single corporate workflow.</p>
    </div>
    <a class="button secondary" href="employee_dashboard.php">Back to dashboard</a>
</div>

<div class="grid two-col">
    <article class="panel">
        <h3>Allocate asset</h3>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">
            <div class="form-grid">
                <label>Employee
                    <select name="employee_id">
                        <option value="">Choose employee</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?= (int)$employee['id'] ?>"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_code'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Asset type<select name="asset_type"><option value="Laptop">Laptop</option><option value="Desktop">Desktop</option><option value="Mobile">Mobile</option><option value="Monitor">Monitor</option><option value="Accessory">Accessory</option></select></label>
                <label>Asset name<input name="asset_name" placeholder="Dell Latitude 7440"></label>
                <label>Serial number<input name="serial_number" placeholder="SN-123456"></label>
                <label class="full">Notes<textarea name="notes" rows="3"></textarea></label>
            </div>
            <div class="form-actions" style="margin-top:18px;">
                <button class="button" type="submit">Assign asset</button>
            </div>
        </form>
    </article>

    <article class="panel">
        <h3>Asset register</h3>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Employee</th><th>Asset</th><th>Serial</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($assets as $asset): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($asset['first_name'] . ' ' . $asset['last_name']) ?></b><br><small><?= htmlspecialchars($asset['employee_code']) ?></small></td>
                            <td><?= htmlspecialchars($asset['asset_name'] ?: $asset['asset_type']) ?></td>
                            <td><?= htmlspecialchars($asset['serial_number']) ?></td>
                            <td><span class="badge active"><?= htmlspecialchars($asset['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</div>
<?php hrPageEnd(); ?>
