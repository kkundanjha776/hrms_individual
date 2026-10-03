<?php
require_once __DIR__ . '/core.php';
hrRequireEmployeeLogin();

$currentEmployee = hrCurrentEmployee($hrDb);
if (!$currentEmployee || !in_array(strtoupper((string)($currentEmployee['role'] ?? 'Employee')), ['HR', 'MD'], true)) {
    header('Location: employee_dashboard.php');
    exit;
}

hrRequireCsrf();
$companyId = hrCompanyId($hrDb);
$states = hrStateList($hrDb);
$search = trim((string)($_GET['q'] ?? ''));
$editEmployeeId = (int)($_GET['edit'] ?? 0);
$editEmployee = null;
if ($editEmployeeId > 0) {
    $stmt = $hrDb->prepare('SELECT * FROM hr_employees WHERE id = ? AND company_id = ? LIMIT 1');
    $stmt->bind_param('ii', $editEmployeeId, $companyId);
    $stmt->execute();
    $editEmployee = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_employee') {
        $deleteId = (int)($_POST['employee_id'] ?? 0);
        if ($deleteId > 0) {
            $hrDb->query('DELETE FROM hr_employees WHERE id=' . $deleteId . ' AND company_id=' . $companyId);
            hrFlash('Employee deleted successfully.');
        }
        header('Location: employees.php');
        exit;
    }

    if ($action === 'update_employee') {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        if ($employeeId > 0) {
            $code = hrEsc($hrDb, (string)($_POST['employee_code'] ?? ''));
            $first = hrEsc($hrDb, (string)($_POST['first_name'] ?? ''));
            $last = hrEsc($hrDb, (string)($_POST['last_name'] ?? ''));
            $email = hrEsc($hrDb, (string)($_POST['email'] ?? ''));
            $phone = hrEsc($hrDb, (string)($_POST['phone'] ?? ''));
            $department = hrEsc($hrDb, (string)($_POST['department'] ?? ''));
            $designation = hrEsc($hrDb, (string)($_POST['designation'] ?? ''));
            $role = strtoupper(trim((string)($_POST['role'] ?? 'Employee')));
            $status = strtoupper(trim((string)($_POST['status'] ?? 'Active')));
            $joinDate = trim((string)($_POST['join_date'] ?? ''));
            $employmentType = hrEsc($hrDb, (string)($_POST['employment_type'] ?? 'Full time'));
            $basic = (float)($_POST['basic_salary'] ?? 0);
            $hra = (float)($_POST['hra'] ?? 0);
            $allowance = (float)($_POST['allowance'] ?? 0);
            $pf = (float)($_POST['pf_rate'] ?? 12);
            $esi = (float)($_POST['esi_rate'] ?? 0.75);
            $tax = (float)($_POST['professional_tax'] ?? 0);
            $dob = trim((string)($_POST['dob'] ?? ''));
            $gender = hrEsc($hrDb, (string)($_POST['gender'] ?? ''));
            $address = hrEsc($hrDb, (string)($_POST['address'] ?? ''));
            $city = hrEsc($hrDb, (string)($_POST['city'] ?? ''));
            $state = hrEsc($hrDb, (string)($_POST['state'] ?? ''));
            $country = hrEsc($hrDb, (string)($_POST['country'] ?? 'India'));
            $qualification = hrEsc($hrDb, (string)($_POST['qualification'] ?? ''));
            $experience = (float)($_POST['experience_years'] ?? 0);
            $emergencyName = hrEsc($hrDb, (string)($_POST['emergency_contact_name'] ?? ''));
            $emergencyPhone = hrEsc($hrDb, (string)($_POST['emergency_contact_phone'] ?? ''));
            $aadhar = hrEsc($hrDb, (string)($_POST['aadhar'] ?? ''));
            $passport = hrEsc($hrDb, (string)($_POST['passport'] ?? ''));

            if ($code === '' || $first === '') {
                hrFlash('Employee code and first name are required.', 'error');
                header('Location: employees.php?edit=' . $employeeId);
                exit;
            }

            $joinSql = $joinDate !== '' ? "'" . $hrDb->real_escape_string($joinDate) . "'" : 'NULL';
            $dobSql = $dob !== '' ? "'" . $hrDb->real_escape_string($dob) . "'" : 'NULL';

            $sql = "UPDATE hr_employees SET
                employee_code = '" . $hrDb->real_escape_string($code) . "',
                first_name = '" . $hrDb->real_escape_string($first) . "',
                last_name = '" . $hrDb->real_escape_string($last) . "',
                email = '" . $hrDb->real_escape_string($email) . "',
                phone = '" . $hrDb->real_escape_string($phone) . "',
                department = '" . $hrDb->real_escape_string($department) . "',
                designation = '" . $hrDb->real_escape_string($designation) . "',
                join_date = $joinSql,
                employment_type = '" . $hrDb->real_escape_string($employmentType) . "',
                basic_salary = " . $basic . ",
                hra = " . $hra . ",
                allowance = " . $allowance . ",
                pf_rate = " . $pf . ",
                esi_rate = " . $esi . ",
                professional_tax = " . $tax . ",
                role = '" . $hrDb->real_escape_string($role ?: 'Employee') . "',
                status = '" . $hrDb->real_escape_string($status ?: 'Active') . "',
                dob = $dobSql,
                gender = '" . $hrDb->real_escape_string($gender) . "',
                address = '" . $hrDb->real_escape_string($address) . "',
                city = '" . $hrDb->real_escape_string($city) . "',
                state = '" . $hrDb->real_escape_string($state) . "',
                country = '" . $hrDb->real_escape_string($country) . "',
                qualification = '" . $hrDb->real_escape_string($qualification) . "',
                experience_years = " . $experience . ",
                emergency_contact_name = '" . $hrDb->real_escape_string($emergencyName) . "',
                emergency_contact_phone = '" . $hrDb->real_escape_string($emergencyPhone) . "',
                aadhar = '" . $hrDb->real_escape_string($aadhar) . "',
                passport = '" . $hrDb->real_escape_string($passport) . "'
            WHERE id = " . $employeeId . " AND company_id = " . $companyId;

            $hrDb->query($sql);
            hrFlash('Employee updated successfully.');
            header('Location: employees.php');
            exit;
        }
    }

    if ($action === 'create_employee') {
        $code = hrEsc($hrDb, (string)($_POST['employee_code'] ?? ''));
        $first = hrEsc($hrDb, (string)($_POST['first_name'] ?? ''));
        if ($code === '' || $first === '') {
            hrFlash('Employee code and first name are required.', 'error');
            header('Location: employees.php#add');
            exit;
        }

        $last = hrEsc($hrDb, (string)($_POST['last_name'] ?? ''));
        $email = hrEsc($hrDb, (string)($_POST['email'] ?? ''));
        $phone = hrEsc($hrDb, (string)($_POST['phone'] ?? ''));
        $department = hrEsc($hrDb, (string)($_POST['department'] ?? ''));
        $designation = hrEsc($hrDb, (string)($_POST['designation'] ?? ''));
        $joinDate = trim((string)($_POST['join_date'] ?? ''));
        $employmentType = hrEsc($hrDb, (string)($_POST['employment_type'] ?? 'Full time'));
        $role = strtoupper(trim((string)($_POST['role'] ?? 'Employee')));
        $status = strtoupper(trim((string)($_POST['status'] ?? 'Active')));
        $basic = (float)($_POST['basic_salary'] ?? 0);
        $hra = (float)($_POST['hra'] ?? 0);
        $allowance = (float)($_POST['allowance'] ?? 0);
        $pf = (float)($_POST['pf_rate'] ?? 12);
        $esi = (float)($_POST['esi_rate'] ?? 0.75);
        $tax = (float)($_POST['professional_tax'] ?? 0);
        $loginPassword = trim((string)($_POST['password'] ?? ''));
        if ($loginPassword === '') {
            $loginPassword = '123456';
        }
        $passwordHash = password_hash($loginPassword, PASSWORD_DEFAULT);
        $dob = trim((string)($_POST['dob'] ?? ''));
        $gender = hrEsc($hrDb, (string)($_POST['gender'] ?? ''));
        $address = hrEsc($hrDb, (string)($_POST['address'] ?? ''));
        $city = hrEsc($hrDb, (string)($_POST['city'] ?? ''));
        $state = hrEsc($hrDb, (string)($_POST['state'] ?? ''));
        $country = hrEsc($hrDb, (string)($_POST['country'] ?? 'India'));
        $qualification = hrEsc($hrDb, (string)($_POST['qualification'] ?? ''));
        $experience = (float)($_POST['experience_years'] ?? 0);
        $emergencyName = hrEsc($hrDb, (string)($_POST['emergency_contact_name'] ?? ''));
        $emergencyPhone = hrEsc($hrDb, (string)($_POST['emergency_contact_phone'] ?? ''));
        $aadhar = hrEsc($hrDb, (string)($_POST['aadhar'] ?? ''));
        $passport = hrEsc($hrDb, (string)($_POST['passport'] ?? ''));

        $joinSql = $joinDate !== '' ? "'" . $hrDb->real_escape_string($joinDate) . "'" : 'NULL';
        $dobSql = $dob !== '' ? "'" . $hrDb->real_escape_string($dob) . "'" : 'NULL';

        $sql = "INSERT INTO hr_employees (
            company_id, employee_code, first_name, last_name, email, phone, department, designation, join_date,
            employment_type, status, role, basic_salary, hra, allowance, pf_rate, esi_rate, professional_tax,
            password_hash, dob, gender, address, city, state, country, qualification, experience_years,
            emergency_contact_name, emergency_contact_phone, aadhar, passport
        ) VALUES (
            " . $companyId . ",
            '" . $hrDb->real_escape_string($code) . "',
            '" . $hrDb->real_escape_string($first) . "',
            '" . $hrDb->real_escape_string($last) . "',
            '" . $hrDb->real_escape_string($email) . "',
            '" . $hrDb->real_escape_string($phone) . "',
            '" . $hrDb->real_escape_string($department) . "',
            '" . $hrDb->real_escape_string($designation) . "',
            $joinSql,
            '" . $hrDb->real_escape_string($employmentType) . "',
            '" . $hrDb->real_escape_string($status ?: 'Active') . "',
            '" . $hrDb->real_escape_string($role ?: 'Employee') . "',
            " . $basic . ",
            " . $hra . ",
            " . $allowance . ",
            " . $pf . ",
            " . $esi . ",
            " . $tax . ",
            '" . $hrDb->real_escape_string($passwordHash) . "',
            $dobSql,
            '" . $hrDb->real_escape_string($gender) . "',
            '" . $hrDb->real_escape_string($address) . "',
            '" . $hrDb->real_escape_string($city) . "',
            '" . $hrDb->real_escape_string($state) . "',
            '" . $hrDb->real_escape_string($country) . "',
            '" . $hrDb->real_escape_string($qualification) . "',
            " . $experience . ",
            '" . $hrDb->real_escape_string($emergencyName) . "',
            '" . $hrDb->real_escape_string($emergencyPhone) . "',
            '" . $hrDb->real_escape_string($aadhar) . "',
            '" . $hrDb->real_escape_string($passport) . "'
        )";

        $hrDb->query($sql);
        hrFlash('Employee added successfully.');
        header('Location: employees.php');
        exit;
    }
}

$employees = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $hrDb->prepare('SELECT * FROM hr_employees WHERE company_id = ? AND (first_name LIKE ? OR last_name LIKE ? OR employee_code LIKE ?) ORDER BY id DESC');
    $stmt->bind_param('isss', $companyId, $like, $like, $like);
    $stmt->execute();
    $employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $result = $hrDb->query('SELECT * FROM hr_employees WHERE company_id = ' . $companyId . ' ORDER BY id DESC');
    $employees = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

hrPageStart('Employees');
?>
<div class="page-head">
    <div>
        <h2>Employee directory</h2>
        <p>One employee master for each company workspace.</p>
    </div>
    <a class="button" href="#add"><i data-lucide="user-plus"></i>Add employee</a>
</div>

<div class="panel">
    <form class="toolbar" method="get">
        <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name or employee code">
        <button class="button secondary" type="submit">Search</button>
        <a class="button secondary" href="import.php"><i data-lucide="file-spreadsheet"></i>Import Excel</a>
    </form>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>EMPLOYEE</th>
                    <th>DEPARTMENT</th>
                    <th>DESIGNATION</th>
                    <th>JOINED</th>
                    <th>MONTHLY GROSS</th>
                    <th>STATUS</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $e): ?>
                    <tr>
                        <td>
                            <b><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></b><br>
                            <small><?= htmlspecialchars($e['employee_code']) ?> · <?= htmlspecialchars($e['email']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($e['department']) ?></td>
                        <td><?= htmlspecialchars($e['designation']) ?></td>
                        <td><?= htmlspecialchars($e['join_date']) ?></td>
                        <td><?= hrMoney((float)$e['basic_salary'] + (float)$e['hra'] + (float)$e['allowance']) ?></td>
                        <td><span class="badge active"><?= htmlspecialchars($e['status']) ?></span></td>
                        <td>
                            <div class="action-group">
                                <a class="button secondary" href="employees.php?edit=<?= (int)$e['id'] ?>#edit">Edit</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this employee?');">
                                    <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">
                                    <input type="hidden" name="action" value="delete_employee">
                                    <input type="hidden" name="employee_id" value="<?= (int)$e['id'] ?>">
                                    <button class="button secondary" type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$employees): ?>
                    <tr><td colspan="7" class="empty">No matching employees.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="add" class="form-card">
    <h3>Add employee</h3>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">
        <input type="hidden" name="action" value="create_employee">

        <h3 style="margin-top:0">Basic information</h3>
        <div class="form-grid">
            <label>Employee code<input name="employee_code" placeholder="EMP-004" required></label>
            <label>First name<input name="first_name" required></label>
            <label>Last name<input name="last_name"></label>
            <label>Work email<input type="email" name="email"></label>
            <label>Phone<input name="phone"></label>
            <label>Date of birth<input type="date" name="dob"></label>
            <label>Gender<select name="gender"><option value="">Select</option><option>Male</option><option>Female</option><option>Other</option></select></label>
            <label>Qualification<input name="qualification" placeholder="B.Tech / MBA"></label>
            <label>Experience years<input type="number" step="0.1" min="0" name="experience_years" value="0"></label>
            <label>Address<textarea name="address" rows="2"></textarea></label>
            <label>City<input name="city"></label>
            <label>State<select name="state"><option value="">Select state</option><?php foreach ($states as $stateOption): ?><option value="<?= htmlspecialchars($stateOption['state_name']) ?>"><?= htmlspecialchars($stateOption['state_name']) ?></option><?php endforeach; ?></select></label>
            <label>Country<input name="country" value="India"></label>
            <label>Emergency contact name<input name="emergency_contact_name"></label>
            <label>Emergency contact phone<input name="emergency_contact_phone"></label>
            <label>Aadhar<input name="aadhar"></label>
            <label>Passport<input name="passport"></label>
        </div>

        <h3 style="margin-top:24px">Joining & payroll setup</h3>
        <div class="form-grid">
            <label>Joining date<input type="date" name="join_date" value="<?= date('Y-m-d') ?>"></label>
            <label>Department<input name="department" placeholder="Engineering"></label>
            <label>Designation<input name="designation" placeholder="Software Engineer"></label>
            <label>Employment type<select name="employment_type"><option>Full time</option><option>Contract</option><option>Intern</option></select></label>
            <label>Employee password<input type="password" name="password" placeholder="Default 123456"></label>
            <label>Role<select name="role"><option>Employee</option><option>HR</option><option>MD</option><option>IT</option></select></label>
            <label>Status<select name="status"><option>Active</option><option>Inactive</option><option>Exited</option></select></label>
            <label>Basic salary<input type="number" step="0.01" name="basic_salary" value="0"></label>
            <label>HRA<input type="number" step="0.01" name="hra" value="0"></label>
            <label>Allowance<input type="number" step="0.01" name="allowance" value="0"></label>
            <label>PF rate<input type="number" step="0.01" name="pf_rate" value="12"></label>
            <label>ESI rate<input type="number" step="0.01" name="esi_rate" value="0.75"></label>
            <label>Professional tax<input type="number" step="0.01" name="professional_tax" value="0"></label>
        </div>

        <div class="form-actions">
            <button class="button" type="submit">Save employee</button>
        </div>
    </form>
</div>

<?php if ($editEmployee): ?>
<div id="edit" class="form-card">
    <h3>Edit employee</h3>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">
        <input type="hidden" name="action" value="update_employee">
        <input type="hidden" name="employee_id" value="<?= (int)$editEmployee['id'] ?>">

        <h3 style="margin-top:0">Basic information</h3>
        <div class="form-grid">
            <label>Employee code<input name="employee_code" value="<?= htmlspecialchars($editEmployee['employee_code']) ?>" required></label>
            <label>First name<input name="first_name" value="<?= htmlspecialchars($editEmployee['first_name']) ?>" required></label>
            <label>Last name<input name="last_name" value="<?= htmlspecialchars($editEmployee['last_name']) ?>"></label>
            <label>Work email<input type="email" name="email" value="<?= htmlspecialchars($editEmployee['email']) ?>"></label>
            <label>Phone<input name="phone" value="<?= htmlspecialchars($editEmployee['phone']) ?>"></label>
            <label>Date of birth<input type="date" name="dob" value="<?= htmlspecialchars((string)($editEmployee['dob'] ?? '')) ?>"></label>
            <label>Gender<select name="gender"><option value="">Select</option><option <?= (($editEmployee['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option><option <?= (($editEmployee['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option><option <?= (($editEmployee['gender'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option></select></label>
            <label>Qualification<input name="qualification" value="<?= htmlspecialchars((string)($editEmployee['qualification'] ?? '')) ?>"></label>
            <label>Experience years<input type="number" step="0.1" min="0" name="experience_years" value="<?= htmlspecialchars((string)($editEmployee['experience_years'] ?? 0)) ?>"></label>
            <label>Address<textarea name="address" rows="2"><?= htmlspecialchars((string)($editEmployee['address'] ?? '')) ?></textarea></label>
            <label>City<input name="city" value="<?= htmlspecialchars((string)($editEmployee['city'] ?? '')) ?>"></label>
            <label>State<select name="state"><option value="">Select state</option><?php foreach ($states as $stateOption): ?><option value="<?= htmlspecialchars($stateOption['state_name']) ?>" <?= ((string)($editEmployee['state'] ?? '') === (string)$stateOption['state_name']) ? 'selected' : '' ?>><?= htmlspecialchars($stateOption['state_name']) ?></option><?php endforeach; ?></select></label>
            <label>Country<input name="country" value="<?= htmlspecialchars((string)($editEmployee['country'] ?? 'India')) ?>"></label>
            <label>Emergency contact name<input name="emergency_contact_name" value="<?= htmlspecialchars((string)($editEmployee['emergency_contact_name'] ?? '')) ?>"></label>
            <label>Emergency contact phone<input name="emergency_contact_phone" value="<?= htmlspecialchars((string)($editEmployee['emergency_contact_phone'] ?? '')) ?>"></label>
            <label>Aadhar<input name="aadhar" value="<?= htmlspecialchars((string)($editEmployee['aadhar'] ?? '')) ?>"></label>
            <label>Passport<input name="passport" value="<?= htmlspecialchars((string)($editEmployee['passport'] ?? '')) ?>"></label>
        </div>

        <h3 style="margin-top:24px">Joining & payroll setup</h3>
        <div class="form-grid">
            <label>Joining date<input type="date" name="join_date" value="<?= htmlspecialchars((string)($editEmployee['join_date'] ?? date('Y-m-d'))) ?>"></label>
            <label>Department<input name="department" value="<?= htmlspecialchars((string)($editEmployee['department'] ?? '')) ?>"></label>
            <label>Designation<input name="designation" value="<?= htmlspecialchars((string)($editEmployee['designation'] ?? '')) ?>"></label>
            <label>Employment type<select name="employment_type"><option <?= (($editEmployee['employment_type'] ?? 'Full time') === 'Full time') ? 'selected' : '' ?>>Full time</option><option <?= (($editEmployee['employment_type'] ?? 'Full time') === 'Contract') ? 'selected' : '' ?>>Contract</option><option <?= (($editEmployee['employment_type'] ?? 'Full time') === 'Intern') ? 'selected' : '' ?>>Intern</option></select></label>
            <label>Role<select name="role"><option <?= (strtoupper((string)($editEmployee['role'] ?? 'Employee')) === 'EMPLOYEE') ? 'selected' : '' ?>>Employee</option><option <?= (strtoupper((string)($editEmployee['role'] ?? 'Employee')) === 'HR') ? 'selected' : '' ?>>HR</option><option <?= (strtoupper((string)($editEmployee['role'] ?? 'Employee')) === 'MD') ? 'selected' : '' ?>>MD</option><option <?= (strtoupper((string)($editEmployee['role'] ?? 'Employee')) === 'IT') ? 'selected' : '' ?>>IT</option></select></label>
            <label>Status<select name="status"><option <?= (($editEmployee['status'] ?? 'Active') === 'Active') ? 'selected' : '' ?>>Active</option><option <?= (($editEmployee['status'] ?? 'Active') === 'Inactive') ? 'selected' : '' ?>>Inactive</option><option <?= (($editEmployee['status'] ?? 'Active') === 'Exited') ? 'selected' : '' ?>>Exited</option></select></label>
            <label>Basic salary<input type="number" step="0.01" name="basic_salary" value="<?= htmlspecialchars((string)($editEmployee['basic_salary'] ?? 0)) ?>"></label>
            <label>HRA<input type="number" step="0.01" name="hra" value="<?= htmlspecialchars((string)($editEmployee['hra'] ?? 0)) ?>"></label>
            <label>Allowance<input type="number" step="0.01" name="allowance" value="<?= htmlspecialchars((string)($editEmployee['allowance'] ?? 0)) ?>"></label>
            <label>PF rate<input type="number" step="0.01" name="pf_rate" value="<?= htmlspecialchars((string)($editEmployee['pf_rate'] ?? 12)) ?>"></label>
            <label>ESI rate<input type="number" step="0.01" name="esi_rate" value="<?= htmlspecialchars((string)($editEmployee['esi_rate'] ?? 0.75)) ?>"></label>
            <label>Professional tax<input type="number" step="0.01" name="professional_tax" value="<?= htmlspecialchars((string)($editEmployee['professional_tax'] ?? 0)) ?>"></label>
        </div>

        <div class="form-actions">
            <button class="button" type="submit">Save changes</button>
            <a class="button secondary" href="employees.php">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php hrPageEnd(); ?>
