<?php
require_once __DIR__ . '/core.php';
hrRequireCsrf();
$companyId = hrCompanyId($hrDb);
$states = hrStateList($hrDb);
$employees = $hrDb->query("SELECT * FROM hr_employees WHERE company_id = $companyId AND status = 'Active' ORDER BY first_name, last_name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_rule') {
        $ruleId = (int)($_POST['rule_id'] ?? 0);
        $day = trim((string)($_POST['day_of_week'] ?? ''));
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $stateName = trim((string)($_POST['state_name'] ?? ''));

        if ($day !== '') {
            if ($ruleId > 0) {
                $sql = "UPDATE hr_weekly_offs SET employee_id = " . ($employeeId > 0 ? (int)$employeeId : 'NULL') . ", state_name = '" . $hrDb->real_escape_string($stateName) . "', day_of_week = '" . $hrDb->real_escape_string($day) . "', is_active = 1 WHERE id = $ruleId AND company_id = $companyId";
                $hrDb->query($sql);
                hrFlash('Weekly off rule updated.');
            } else {
                $matchSql = "SELECT id FROM hr_weekly_offs WHERE company_id = $companyId AND day_of_week = '" . $hrDb->real_escape_string($day) . "'";
                if ($employeeId > 0) {
                    $matchSql .= " AND employee_id = $employeeId";
                } else {
                    $matchSql .= " AND employee_id IS NULL AND COALESCE(state_name, '') = '" . $hrDb->real_escape_string($stateName) . "'";
                }
                $matchSql .= " LIMIT 1";
                $matchRow = $hrDb->query($matchSql)->fetch_assoc();

                if ($matchRow) {
                    $hrDb->query("UPDATE hr_weekly_offs SET employee_id = " . ($employeeId > 0 ? (int)$employeeId : 'NULL') . ", state_name = '" . $hrDb->real_escape_string($stateName) . "', is_active = 1 WHERE id = " . (int)$matchRow['id'] . " AND company_id = $companyId");
                    hrFlash('Weekly off rule updated.');
                } else {
                    $insertSql = "INSERT INTO hr_weekly_offs (company_id, employee_id, state_name, day_of_week, is_active) VALUES ($companyId, " . ($employeeId > 0 ? $employeeId : 'NULL') . ", '" . $hrDb->real_escape_string($stateName) . "', '" . $hrDb->real_escape_string($day) . "', 1)";
                    $hrDb->query($insertSql);
                    hrFlash('Weekly off rule saved.');
                }
            }

            if ($employeeId <= 0 && $stateName === '') {
                $scheduleSql = "SELECT id FROM hr_company_work_schedule WHERE company_id = $companyId AND day_of_week = '" . $hrDb->real_escape_string($day) . "' LIMIT 1";
                $scheduleRow = $hrDb->query($scheduleSql)->fetch_assoc();
                if ($scheduleRow) {
                    $hrDb->query("UPDATE hr_company_work_schedule SET is_working_day = 0 WHERE company_id = $companyId AND day_of_week = '" . $hrDb->real_escape_string($day) . "'");
                } else {
                    $hrDb->query("INSERT INTO hr_company_work_schedule (company_id, day_of_week, is_working_day, office_start_time, office_end_time) VALUES ($companyId, '" . $hrDb->real_escape_string($day) . "', 0, '09:00:00', '18:00:00')");
                }
            }
        }
    }

    if ($action === 'save_schedule') {
        $day = trim((string)($_POST['day_of_week'] ?? ''));
        $isWorking = isset($_POST['is_working_day']) ? 1 : 0;
        $officeStart = trim((string)($_POST['office_start_time'] ?? '09:00'));
        $officeEnd = trim((string)($_POST['office_end_time'] ?? '18:00'));

        if ($day !== '') {
            $officeStart = $officeStart === '' ? '09:00' : $officeStart;
            $officeEnd = $officeEnd === '' ? '18:00' : $officeEnd;

            $row = $hrDb->query("SELECT id FROM hr_company_work_schedule WHERE company_id = $companyId AND day_of_week = '" . $hrDb->real_escape_string($day) . "' LIMIT 1")->fetch_assoc();
            if ($row) {
                $hrDb->query("UPDATE hr_company_work_schedule SET is_working_day = $isWorking, office_start_time = '" . $hrDb->real_escape_string($officeStart) . "', office_end_time = '" . $hrDb->real_escape_string($officeEnd) . "' WHERE company_id = $companyId AND day_of_week = '" . $hrDb->real_escape_string($day) . "'");
            } else {
                $hrDb->query("INSERT INTO hr_company_work_schedule (company_id, day_of_week, is_working_day, office_start_time, office_end_time) VALUES ($companyId, '" . $hrDb->real_escape_string($day) . "', $isWorking, '" . $hrDb->real_escape_string($officeStart) . "', '" . $hrDb->real_escape_string($officeEnd) . "')");
            }
            hrFlash('Office hours and rest day saved.');
        }
    }

    if ($action === 'delete_rule') {
        $ruleId = (int)($_POST['rule_id'] ?? 0);
        $day = trim((string)($_POST['day_of_week'] ?? ''));
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $stateName = trim((string)($_POST['state_name'] ?? ''));
        $deleted = false;

        if ($ruleId > 0) {
            $existing = $hrDb->query("SELECT id FROM hr_weekly_offs WHERE id = $ruleId AND company_id = $companyId LIMIT 1")->fetch_assoc();
            if ($existing) {
                $hrDb->query("DELETE FROM hr_weekly_offs WHERE id = $ruleId AND company_id = $companyId");
                $deleted = true;
            }
        }

        if (!$deleted && $day !== '') {
            $deleteSql = "DELETE FROM hr_weekly_offs WHERE company_id = $companyId AND day_of_week = '" . $hrDb->real_escape_string($day) . "'";
            if ($employeeId > 0) {
                $deleteSql .= " AND employee_id = $employeeId";
            } else {
                $deleteSql .= " AND employee_id IS NULL";
                $stateValue = $hrDb->real_escape_string($stateName);
                $deleteSql .= " AND (COALESCE(state_name, '') = '" . $stateValue . "' OR state_name IS NULL OR state_name = '')";
            }
            $hrDb->query($deleteSql);
            $deleted = $hrDb->affected_rows > 0;
        }

        if ($employeeId <= 0 && $stateName === '' && $day !== '') {
            $hrDb->query("UPDATE hr_company_work_schedule SET is_working_day = 1 WHERE company_id = $companyId AND day_of_week = '" . $hrDb->real_escape_string($day) . "'");
        }

        if ($deleted) {
            hrFlash('Weekly off rule removed.');
        } else {
            hrFlash('Rule already removed or not found.', 'error');
        }
    }

    header('Location: weekly_off.php');
    exit;
}

$editRuleId = (int)($_GET['edit_rule_id'] ?? 0);
$editRule = null;
if ($editRuleId > 0) {
    $editRule = $hrDb->query("SELECT * FROM hr_weekly_offs WHERE id = $editRuleId AND company_id = $companyId LIMIT 1")->fetch_assoc();
}

$rules = $hrDb->query("SELECT w.*, e.first_name, e.last_name, e.employee_code FROM hr_weekly_offs w LEFT JOIN hr_employees e ON e.id = w.employee_id WHERE w.company_id = $companyId ORDER BY FIELD(w.day_of_week, 'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), e.first_name, w.state_name")->fetch_all(MYSQLI_ASSOC);
$companySchedule = hrGetCompanyWorkSchedule($hrDb, $companyId);
$scheduleSelectedDay = trim((string)($_GET['schedule_day'] ?? 'Monday'));
if (!isset($companySchedule[$scheduleSelectedDay])) {
    $scheduleSelectedDay = 'Monday';
}
$scheduleSelected = $companySchedule[$scheduleSelectedDay] ?? ['is_working_day' => true, 'office_start_time' => '09:00:00', 'office_end_time' => '18:00:00'];

hrPageStart('Weekly off');
?>
<div class="page-head">
    <div>
        <h2>Weekly off setup</h2>
        <p>Configure default, state-wise, or employee-wise weekly-off rules.</p>
    </div>
</div>

<div class="panel" style="margin-top:18px;">
    <h3>Working day and office timing</h3>
    <form method="post" class="toolbar" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;align-items:end;">
        <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">
        <input type="hidden" name="action" value="save_schedule">

        <label>Day of week
            <select name="day_of_week" required>
                <option value="">Select</option>
                <?php foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $dayName): ?>
                    <option value="<?= htmlspecialchars($dayName) ?>" <?= $scheduleSelectedDay === $dayName ? 'selected' : '' ?>><?= htmlspecialchars($dayName) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <input type="checkbox" name="is_working_day" value="1" <?= !empty($scheduleSelected['is_working_day']) ? 'checked' : '' ?>>
            Mark as working day
        </label>

        <label>Office start time
            <input type="time" name="office_start_time" value="<?= htmlspecialchars(substr($scheduleSelected['office_start_time'], 0, 5)) ?>">
        </label>

        <label>Office end time
            <input type="time" name="office_end_time" value="<?= htmlspecialchars(substr($scheduleSelected['office_end_time'], 0, 5)) ?>">
        </label>

        <div>
            <button class="button" type="submit">Save schedule</button>
        </div>
    </form>
</div>

<div class="panel" style="margin-top:18px;">
    <h3>Current office schedule</h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Status</th>
                    <th>Office time</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $dayName): ?>
                    <?php $dayConfig = $companySchedule[$dayName] ?? ['is_working_day' => !in_array($dayName, ['Saturday','Sunday'], true), 'office_start_time' => '09:00:00', 'office_end_time' => '18:00:00']; ?>
                    <tr>
                        <td><?= htmlspecialchars($dayName) ?></td>
                        <td><?= $dayConfig['is_working_day'] ? 'Working day' : 'Rest day' ?></td>
                        <td><?= htmlspecialchars($dayConfig['is_working_day'] ? substr($dayConfig['office_start_time'], 0, 5) . ' - ' . substr($dayConfig['office_end_time'], 0, 5) : 'Off') ?></td>
                        <td>
                            <a class="button secondary" href="weekly_off.php?schedule_day=<?= urlencode($dayName) ?>">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel" style="margin-top:18px;">
    <h3>Add weekly off rule</h3>
    <form method="post" class="toolbar" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;align-items:end;">
        <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">
        <input type="hidden" name="action" value="save_rule">
        <?php if ($editRule): ?>
            <input type="hidden" name="rule_id" value="<?= (int)$editRule['id'] ?>">
        <?php endif; ?>

        <label>Day of week
            <select name="day_of_week" required>
                <option value="">Select</option>
                <?php foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $dayName): ?>
                    <option value="<?= htmlspecialchars($dayName) ?>" <?= ($editRule['day_of_week'] ?? '') === $dayName ? 'selected' : '' ?>><?= htmlspecialchars($dayName) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Employee (optional)
            <select name="employee_id">
                <option value="">All employees</option>
                <?php foreach ($employees as $employee): ?>
                    <option value="<?= (int)$employee['id'] ?>" <?= (($editRule['employee_id'] ?? 0) == (int)$employee['id']) ? 'selected' : '' ?>><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?> (<?= htmlspecialchars($employee['employee_code']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>State (optional)
            <select name="state_name">
                <option value="">Company default</option>
                <?php foreach ($states as $state): ?>
                    <option value="<?= htmlspecialchars($state['state_name']) ?>" <?= (($editRule['state_name'] ?? '') === $state['state_name']) ? 'selected' : '' ?>><?= htmlspecialchars($state['state_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <div>
            <button class="button" type="submit"><?= $editRule ? 'Update rule' : 'Save rule' ?></button>
            <?php if ($editRule): ?>
                <a class="button secondary" href="weekly_off.php">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="panel" style="margin-top:18px;">
    <h3>Current weekly off rules</h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Scope</th>
                    <th>State</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rules): ?>
                    <?php foreach ($rules as $rule): ?>
                        <tr>
                            <td><?= htmlspecialchars($rule['day_of_week']) ?></td>
                            <td><?= !empty($rule['employee_id']) ? 'Employee' : (!empty($rule['state_name']) ? 'State' : 'Company') ?></td>
                            <td><?= htmlspecialchars($rule['state_name'] ?: ($rule['employee_id'] ? '-' : 'All')) ?></td>
                            <td>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                    <a class="button secondary" href="weekly_off.php?edit_rule_id=<?= (int)$rule['id'] ?>">Edit</a>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Remove this weekly off rule?');">
                                        <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">
                                        <input type="hidden" name="action" value="delete_rule">
                                        <input type="hidden" name="rule_id" value="<?= (int)$rule['id'] ?>">
                                        <input type="hidden" name="day_of_week" value="<?= htmlspecialchars($rule['day_of_week']) ?>">
                                        <input type="hidden" name="employee_id" value="<?= (int)($rule['employee_id'] ?? 0) ?>">
                                        <input type="hidden" name="state_name" value="<?= htmlspecialchars($rule['state_name'] ?? '') ?>">
                                        <button class="button secondary" type="submit">Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="empty">No weekly off rules configured yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php hrPageEnd(); ?>
