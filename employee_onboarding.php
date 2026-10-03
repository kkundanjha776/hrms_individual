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

    $code = trim((string)($_POST['employee_code'] ?? ''));
    $first = trim((string)($_POST['first_name'] ?? ''));
    $last = trim((string)($_POST['last_name'] ?? ''));
    if ($code === '' || $first === '') {
        hrFlash('Employee code and first name are required.', 'error');
        header('Location: employee_onboarding.php');
        exit;
    }

    $duplicate = $hrDb->query("SELECT id FROM hr_employees WHERE company_id=$companyId AND employee_code='" . $hrDb->real_escape_string($code) . "' LIMIT 1")->fetch_assoc();
    if ($duplicate) {
        hrFlash('This employee code already exists in this company.', 'error');
        header('Location: employee_onboarding.php');
        exit;
    }

    $role = strtoupper(trim((string)($_POST['role'] ?? 'Employee')));
    if ($role === '') {
        $role = 'Employee';
    }
    $loginPassword = trim((string)($_POST['password'] ?? ''));
    if ($loginPassword === '') {
        $loginPassword = '123456';
    }

    $stmt = $hrDb->prepare('INSERT INTO hr_employees (company_id, employee_code, first_name, last_name, email, phone, department, designation, join_date, employment_type, status, role, password_hash, dob, gender, address, city, state, country, qualification, experience_years, emergency_contact_name, emergency_contact_phone, aadhar, passport) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $joinDate = $_POST['join_date'] ?? date('Y-m-d');
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $department = trim((string)($_POST['department'] ?? ''));
    $designation = trim((string)($_POST['designation'] ?? ''));
    $employmentType = trim((string)($_POST['employment_type'] ?? 'Full time'));
    $status = 'Active';
    $hash = password_hash($loginPassword, PASSWORD_DEFAULT);
    $dob = $_POST['dob'] ?? null;
    $gender = trim((string)($_POST['gender'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $state = trim((string)($_POST['state'] ?? ''));
    $country = trim((string)($_POST['country'] ?? 'India'));
    $qualification = trim((string)($_POST['qualification'] ?? ''));
    $experienceYears = (float)($_POST['experience_years'] ?? 0);
    $emergencyContactName = trim((string)($_POST['emergency_contact_name'] ?? ''));
    $emergencyContactPhone = trim((string)($_POST['emergency_contact_phone'] ?? ''));
    $aadhar = trim((string)($_POST['aadhar'] ?? ''));
    $passport = trim((string)($_POST['passport'] ?? ''));
    $bindTypes = 'i' . str_repeat('s', 19) . 'd' . str_repeat('s', 4);
    $stmt->bind_param(
        $bindTypes,
        $companyId,
        $code,
        $first,
        $last,
        $email,
        $phone,
        $department,
        $designation,
        $joinDate,
        $employmentType,
        $status,
        $role,
        $hash,
        $dob,
        $gender,
        $address,
        $city,
        $state,
        $country,
        $qualification,
        $experienceYears,
        $emergencyContactName,
        $emergencyContactPhone,
        $aadhar,
        $passport
    );
    $stmt->execute();
    $stmt->close();

    $employeeId = (int)$hrDb->insert_id;
    $probation = trim((string)($_POST['probation_period'] ?? '6 months'));
    $offerStatus = trim((string)($_POST['offer_status'] ?? 'Pending'));
    $backgroundCheck = trim((string)($_POST['background_check'] ?? 'Pending'));
    $assetDeclaration = trim((string)($_POST['asset_declaration'] ?? 'Pending'));
    $documentsUploaded = trim((string)($_POST['documents_uploaded'] ?? 'No'));
    $notes = trim((string)($_POST['notes'] ?? ''));

    $hrDb->query("INSERT INTO hr_employee_onboarding (company_id, employee_id, joining_date, probation_period, offer_status, background_check, asset_declaration, documents_uploaded, notes) VALUES ($companyId, $employeeId, '" . $hrDb->real_escape_string($joinDate) . "', '" . $hrDb->real_escape_string($probation) . "', '" . $hrDb->real_escape_string($offerStatus) . "', '" . $hrDb->real_escape_string($backgroundCheck) . "', '" . $hrDb->real_escape_string($assetDeclaration) . "', '" . $hrDb->real_escape_string($documentsUploaded) . "', '" . $hrDb->real_escape_string($notes) . "') ON DUPLICATE KEY UPDATE joining_date=VALUES(joining_date), probation_period=VALUES(probation_period), offer_status=VALUES(offer_status), background_check=VALUES(background_check), asset_declaration=VALUES(asset_declaration), documents_uploaded=VALUES(documents_uploaded), notes=VALUES(notes)");

    hrFlash('Employee basic profile created successfully.');
    header('Location: employee_joining.php?employee_id=' . $employeeId);
    exit;
}

$employees = $hrDb->query("SELECT * FROM hr_employees WHERE company_id=$companyId ORDER BY first_name, last_name")->fetch_all(MYSQLI_ASSOC);
hrPageStart('Employee onboarding');
?>
<div class="page-head">
    <div>
        <h2>Employee onboarding</h2>
        <p>Create the employee profile first. Salary details and monthly components are configured in the separate payroll setup page.</p>
    </div>
    <a class="button secondary" href="employees.php">Back to employees</a>
</div>

<div class="grid two-col">
    <article class="panel">
        <h3>Basic employee information</h3>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">
            <div class="form-grid">
                <label>Employee code<input name="employee_code" placeholder="EMP-010" required></label>
                <label>Role<select name="role"><option value="Employee">Employee</option><option value="HR">HR</option><option value="IT">IT</option><option value="MD">MD</option></select></label>
                <label>First name<input name="first_name" required></label>
                <label>Last name<input name="last_name"></label>
                <label>Work email<input type="email" name="email"></label>
                <label>Phone<input name="phone"></label>
                <label>Department<input name="department" placeholder="Engineering"></label>
                <label>Designation<input name="designation" placeholder="Software Engineer"></label>
                <label>Joining date<input type="date" name="join_date" value="<?= date('Y-m-d') ?>"></label>
                <label>Employment type<select name="employment_type"><option>Full time</option><option>Contract</option><option>Intern</option></select></label>
                <label>Employee password<input type="password" name="password" placeholder="Default: 123456"></label>
                <label>Date of birth<input type="date" name="dob"></label>
                <label>Gender<select name="gender"><option value="">Select</option><option>Male</option><option>Female</option><option>Other</option></select></label>
                <label>Qualification<input name="qualification"></label>
                <label>Experience years<input type="number" step="0.1" min="0" name="experience_years" value="0"></label>
                <label>City<input name="city"></label>
                <label>State<input name="state"></label>
                <label>Country<input name="country" value="India"></label>
                <label>Emergency contact name<input name="emergency_contact_name"></label>
                <label>Emergency contact phone<input name="emergency_contact_phone"></label>
                <label>Aadhar<input name="aadhar"></label>
                <label>Passport<input name="passport"></label>
                <label class="full">Address<textarea name="address" rows="2"></textarea></label>
            </div>
            <h3 style="margin-top:22px;">Onboarding checklist</h3>
            <div class="form-grid">
                <label>Probation period<input name="probation_period" value="6 months"></label>
                <label>Offer status<select name="offer_status"><option>Pending</option><option>Sent</option><option>Accepted</option><option>Rejected</option></select></label>
                <label>Background check<select name="background_check"><option>Pending</option><option>Passed</option><option>Failed</option></select></label>
                <label>Asset declaration<select name="asset_declaration"><option>Pending</option><option>Completed</option></select></label>
                <label>Documents uploaded<select name="documents_uploaded"><option>No</option><option>Yes</option></select></label>
                <label class="full">Notes<textarea name="notes" rows="2"></textarea></label>
            </div>
            <div class="form-actions" style="margin-top:18px;">
                <button class="button" type="submit">Create employee</button>
            </div>
        </form>
    </article>

    <article class="panel">
        <h3>Recent hires</h3>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Employee</th><th>Role</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($employees, 0, 8) as $employee): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></b><br><small><?= htmlspecialchars($employee['employee_code']) ?></small></td>
                            <td><?= htmlspecialchars($employee['role']) ?></td>
                            <td><span class="badge active"><?= htmlspecialchars($employee['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</div>
<?php hrPageEnd(); ?>
