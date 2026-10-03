<?php
require_once __DIR__ . '/core.php';
hrRequireEmployeeLogin();
$currentEmployee = hrCurrentEmployee($hrDb);
if (!hrIsMenuAdmin($currentEmployee)) {
    header('Location: employee_dashboard.php');
    exit;
}

hrRequireCsrf();
$companyId = hrCompanyId($hrDb);
$employees = $hrDb->query("SELECT id, employee_code, first_name, last_name, role FROM hr_employees WHERE company_id = $companyId AND status = 'Active' AND role NOT IN ('HR', 'MD') ORDER BY first_name, last_name")->fetch_all(MYSQLI_ASSOC);
$selectedEmployeeId = (int)($_GET['employee_id'] ?? $_POST['employee_id'] ?? ($employees[0]['id'] ?? 0));
$selectedEmployee = null;
foreach ($employees as $employee) {
    if ((int)$employee['id'] === $selectedEmployeeId) {
        $selectedEmployee = $employee;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedEmployee) {
    $allowed = array_map('strval', (array)($_POST['menu_keys'] ?? []));
    $menuItems = hrMenuItems();
    $delete = $hrDb->prepare('DELETE FROM hr_menu_permissions WHERE company_id = ? AND employee_id = ?');
    $delete->bind_param('ii', $companyId, $selectedEmployeeId);
    $delete->execute();
    $delete->close();

    $insert = $hrDb->prepare('INSERT INTO hr_menu_permissions (company_id, employee_id, menu_key, is_allowed) VALUES (?, ?, ?, ?)');
    foreach ($menuItems as $menuKey => $_meta) {
        $isAllowed = in_array($menuKey, $allowed, true) ? 1 : 0;
        $insert->bind_param('iisi', $companyId, $selectedEmployeeId, $menuKey, $isAllowed);
        $insert->execute();
    }
    $insert->close();
    hrFlash('Menu access saved for ' . $selectedEmployee['first_name'] . '.');
    header('Location: menu_access.php?employee_id=' . $selectedEmployeeId);
    exit;
}

$currentAccess = [];
if ($selectedEmployee) {
    $result = $hrDb->query("SELECT menu_key FROM hr_menu_permissions WHERE company_id = $companyId AND employee_id = $selectedEmployeeId AND is_allowed = 1");
    while ($row = $result->fetch_assoc()) {
        $currentAccess[] = $row['menu_key'];
    }
}
$menuItems = hrMenuItems();
hrPageStart('Menu access');
?>
<div class="page-head">
    <div>
        <h2>Employee menu access</h2>
        <p>Choose which workspace sections each employee can see.</p>
    </div>
</div>
<div class="grid">
    <div class="form-card">
        <h3>Select employee</h3>
        <?php if ($employees): ?>
            <form method="get">
                <label>Employee
                    <select name="employee_id" onchange="this.form.submit()">
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?= (int)$employee['id'] ?>" <?= (int)$employee['id'] === $selectedEmployeeId ? 'selected' : '' ?>><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_code'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>
        <?php else: ?>
            <p class="empty">No regular employees found.</p>
        <?php endif; ?>
    </div>
    <div class="form-card">
        <h3>Allowed menu items</h3>
        <?php if ($selectedEmployee): ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">
                <input type="hidden" name="employee_id" value="<?= $selectedEmployeeId ?>">
                <div class="metric-list">
                    <?php foreach ($menuItems as $menuKey => [$icon, $label]): ?>
                        <label class="check-row"><input type="checkbox" name="menu_keys[]" value="<?= htmlspecialchars($menuKey) ?>" <?= in_array($menuKey, $currentAccess, true) ? 'checked' : '' ?>> <span><?= htmlspecialchars($label) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <div class="form-actions"><button class="button" type="submit">Save menu access</button></div>
            </form>
        <?php else: ?>
            <p class="empty">Select an employee to configure access.</p>
        <?php endif; ?>
    </div>
</div>
<?php hrPageEnd(); ?>
