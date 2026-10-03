<?php
require_once __DIR__.'/core.php';
hrRequireCsrf();
$companyId = hrCompanyId($hrDb);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_holiday') {
        $stateName = trim((string)($_POST['state_name'] ?? ''));
        $name = trim((string)($_POST['holiday_name'] ?? ''));
        $date = (string)($_POST['holiday_date'] ?? '');
        $desc = trim((string)($_POST['description'] ?? ''));
        $repeat = isset($_POST['repeat_annually']) ? 1 : 0;

        if ($stateName === '' || $name === '' || $date === '') {
            hrFlash('State, holiday name and date are required.', 'error');
        } else {
            $st = $hrDb->prepare('INSERT INTO hr_holidays (company_id, state_name, holiday_name, holiday_date, repeat_annually, description) VALUES (?, ?, ?, ?, ?, ?)');
            $st->bind_param('isssis', $companyId, $stateName, $name, $date, $repeat, $desc);
            if ($st->execute()) {
                hrFlash('Holiday saved successfully.');
            } else {
                hrFlash('This holiday already exists for this company/state.', 'error');
            }
            $st->close();
        }
    } elseif ($action === 'delete_holiday') {
        $holidayId = (int)($_POST['holiday_id'] ?? 0);
        if ($holidayId > 0) {
            $st = $hrDb->prepare('DELETE FROM hr_holidays WHERE id = ? AND company_id = ?');
            $st->bind_param('ii', $holidayId, $companyId);
            $st->execute();
            $st->close();
            hrFlash('Holiday removed.');
        }
    }

    header('Location: holiday.php');
    exit;
}

$states = hrStateList($hrDb);
$holidays = $hrDb->query("SELECT * FROM hr_holidays WHERE company_id = $companyId ORDER BY state_name ASC, holiday_date ASC")->fetch_all(MYSQLI_ASSOC);
hrPageStart('Holiday setup');
?>
<div class="page-head">
    <div>
        <h2>Holiday setup</h2>
        <p>Manage official holidays and recurring company leaves by state.</p>
    </div>
</div>

<div class="grid">
    <div class="form-card">
        <h3>Add holiday</h3>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">
            <input type="hidden" name="action" value="add_holiday">
            <div class="form-grid">
                <label>State
                    <select name="state_name" required>
                        <option value="">Select state</option>
                        <?php foreach ($states as $state): ?>
                            <option value="<?= htmlspecialchars($state['state_name']) ?>"><?= htmlspecialchars($state['state_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Holiday name
                    <input name="holiday_name" placeholder="Diwali" required>
                </label>
                <label>Holiday date
                    <input type="date" name="holiday_date" required>
                </label>
                <label class="full">
                    <input type="checkbox" name="repeat_annually" checked> Repeat every year
                </label>
                <label class="full">Description
                    <textarea name="description" placeholder="Optional notes for this holiday"></textarea>
                </label>
            </div>
            <div class="form-actions">
                <button class="button" type="submit">Save holiday</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <h3>Company holiday list</h3>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>STATE</th>
                        <th>NAME</th>
                        <th>DATE</th>
                        <th>RECURS</th>
                        <th>DESCRIPTION</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($holidays as $holiday): ?>
                        <tr>
                            <td><?= htmlspecialchars($holiday['state_name'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($holiday['holiday_name']) ?></td>
                            <td><?= htmlspecialchars($holiday['holiday_date']) ?></td>
                            <td><?= $holiday['repeat_annually'] ? 'Yearly' : 'One-time' ?></td>
                            <td><?= htmlspecialchars($holiday['description'] ?: '-') ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Delete this holiday?');">
                                    <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">
                                    <input type="hidden" name="action" value="delete_holiday">
                                    <input type="hidden" name="holiday_id" value="<?= (int)$holiday['id'] ?>">
                                    <button class="button secondary" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$holidays): ?>
                        <tr>
                            <td colspan="6" class="empty">No holidays set yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php hrPageEnd(); ?>
