<?php
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/functions.php';
date_default_timezone_set('Asia/Kolkata');

// Keep HRMS sessions inside this app so local XAMPP permission differences do not leak warnings into pages.
$hrSessionDir = __DIR__ . '/sessions';
if (!is_dir($hrSessionDir)) { mkdir($hrSessionDir, 0775, true); }
session_save_path($hrSessionDir);
startSecureSession();
$hrDb = getDBConnection();

function hrEnsureSchema(mysqli $db): void {
    $sql = [
        "CREATE TABLE IF NOT EXISTS hr_companies (id INT AUTO_INCREMENT PRIMARY KEY, company_name VARCHAR(160) NOT NULL, company_code VARCHAR(30) NOT NULL UNIQUE, email VARCHAR(120) DEFAULT '', phone VARCHAR(30) DEFAULT '', address TEXT, logo_url VARCHAR(255) DEFAULT '', primary_color VARCHAR(12) DEFAULT '#be185d', currency VARCHAR(10) DEFAULT 'INR', payroll_day TINYINT UNSIGNED DEFAULT 26, status ENUM('Active','Inactive') DEFAULT 'Active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS hr_departments (id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NOT NULL, name VARCHAR(100) NOT NULL, UNIQUE KEY uq_hr_dept (company_id,name), FOREIGN KEY (company_id) REFERENCES hr_companies(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS hr_employees (id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NOT NULL, employee_code VARCHAR(40) NOT NULL, first_name VARCHAR(80) NOT NULL, last_name VARCHAR(80) DEFAULT '', email VARCHAR(120) DEFAULT '', phone VARCHAR(30) DEFAULT '', department VARCHAR(100) DEFAULT '', designation VARCHAR(100) DEFAULT '', join_date DATE DEFAULT NULL, employment_type VARCHAR(30) DEFAULT 'Full time', status ENUM('Active','Inactive','Exited') DEFAULT 'Active', role VARCHAR(30) NOT NULL DEFAULT 'Employee', basic_salary DECIMAL(12,2) DEFAULT 0, hra DECIMAL(12,2) DEFAULT 0, allowance DECIMAL(12,2) DEFAULT 0, pf_rate DECIMAL(5,2) DEFAULT 12, esi_rate DECIMAL(5,2) DEFAULT 0.75, professional_tax DECIMAL(12,2) DEFAULT 0, bank_name VARCHAR(100) DEFAULT '', bank_account VARCHAR(60) DEFAULT '', pan VARCHAR(20) DEFAULT '', dob DATE DEFAULT NULL, gender VARCHAR(20) DEFAULT '', address TEXT, city VARCHAR(80) DEFAULT '', state VARCHAR(80) DEFAULT '', country VARCHAR(80) DEFAULT '', qualification VARCHAR(150) DEFAULT '', experience_years DECIMAL(4,1) DEFAULT 0, emergency_contact_name VARCHAR(120) DEFAULT '', emergency_contact_phone VARCHAR(30) DEFAULT '', aadhar VARCHAR(20) DEFAULT '', passport VARCHAR(30) DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_employee (company_id,employee_code), KEY idx_hr_employee_company (company_id), FOREIGN KEY (company_id) REFERENCES hr_companies(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS hr_employee_onboarding (id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NOT NULL, employee_id INT NOT NULL, joining_date DATE DEFAULT NULL, probation_period VARCHAR(50) DEFAULT '6 months', offer_status ENUM('Pending','Sent','Accepted','Rejected') DEFAULT 'Pending', background_check ENUM('Pending','Passed','Failed') DEFAULT 'Pending', asset_declaration ENUM('Pending','Completed') DEFAULT 'Pending', documents_uploaded ENUM('No','Yes') DEFAULT 'No', notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_employee_onboarding (employee_id), FOREIGN KEY (company_id) REFERENCES hr_companies(id) ON DELETE CASCADE, FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS hr_employee_payroll_setup (id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NOT NULL, employee_id INT NOT NULL, pay_cycle VARCHAR(30) DEFAULT 'Monthly', gross_salary DECIMAL(12,2) DEFAULT 0, net_salary DECIMAL(12,2) DEFAULT 0, effective_date DATE DEFAULT NULL, bank_name VARCHAR(100) DEFAULT '', bank_account VARCHAR(60) DEFAULT '', notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_payroll_setup (employee_id), FOREIGN KEY (company_id) REFERENCES hr_companies(id) ON DELETE CASCADE, FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS hr_employee_assets (id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NOT NULL, employee_id INT NOT NULL, asset_type VARCHAR(80) DEFAULT '', asset_name VARCHAR(120) DEFAULT '', serial_number VARCHAR(120) DEFAULT '', assigned_date DATE DEFAULT NULL, returned_date DATE DEFAULT NULL, status ENUM('Assigned','Returned','Damaged') DEFAULT 'Assigned', issued_by VARCHAR(120) DEFAULT '', notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_hr_asset_employee (employee_id), FOREIGN KEY (company_id) REFERENCES hr_companies(id) ON DELETE CASCADE, FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS hr_attendance (id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NOT NULL, employee_id INT NOT NULL, attendance_date DATE NOT NULL, status ENUM('Present','Late','Absent','Paid Leave','Work From Home') DEFAULT 'Present', check_in TIME DEFAULT NULL, check_out TIME DEFAULT NULL, UNIQUE KEY uq_hr_attendance (employee_id,attendance_date), FOREIGN KEY (company_id) REFERENCES hr_companies(id) ON DELETE CASCADE, FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS hr_attendance_logs (id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NOT NULL, employee_id INT NOT NULL, log_date DATE NOT NULL, punch_type ENUM('check_in','check_out') NOT NULL, punch_time TIME NOT NULL, source VARCHAR(30) DEFAULT 'manual', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_hr_attendance_logs_employee_date (employee_id,log_date), KEY idx_hr_attendance_logs_company_date (company_id,log_date), FOREIGN KEY (company_id) REFERENCES hr_companies(id) ON DELETE CASCADE, FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS hr_weekly_offs (id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NOT NULL, employee_id INT NULL, state_name VARCHAR(80) DEFAULT '', day_of_week ENUM('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_weekly_off (company_id, employee_id, state_name, day_of_week), KEY idx_hr_weekly_off_company (company_id), FOREIGN KEY (company_id) REFERENCES hr_companies(id) ON DELETE CASCADE, FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS hr_states (id INT AUTO_INCREMENT PRIMARY KEY, state_name VARCHAR(80) NOT NULL UNIQUE, state_code VARCHAR(10) NOT NULL DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS hr_leave_requests (id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NOT NULL, employee_id INT NOT NULL, leave_type VARCHAR(50) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, reason VARCHAR(255) DEFAULT '', status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (company_id) REFERENCES hr_companies(id) ON DELETE CASCADE, FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS hr_holidays (id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NOT NULL, state_name VARCHAR(80) NOT NULL DEFAULT '', holiday_name VARCHAR(120) NOT NULL, holiday_date DATE NOT NULL, repeat_annually TINYINT(1) NOT NULL DEFAULT 1, description VARCHAR(255) DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_company_holiday (company_id,state_name,holiday_date,holiday_name), FOREIGN KEY (company_id) REFERENCES hr_companies(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS hr_company_work_schedule (id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NOT NULL, day_of_week ENUM('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL, is_working_day TINYINT(1) NOT NULL DEFAULT 1, office_start_time TIME NOT NULL DEFAULT '09:00:00', office_end_time TIME NOT NULL DEFAULT '18:00:00', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_company_work_schedule (company_id, day_of_week), FOREIGN KEY (company_id) REFERENCES hr_companies(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS hr_payroll_runs (id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NOT NULL, payroll_month CHAR(7) NOT NULL, status ENUM('Draft','Finalized') DEFAULT 'Draft', processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_payroll (company_id,payroll_month), FOREIGN KEY (company_id) REFERENCES hr_companies(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS hr_payslips (id INT AUTO_INCREMENT PRIMARY KEY, payroll_run_id INT NOT NULL, employee_id INT NOT NULL, paid_days DECIMAL(5,2) DEFAULT 26, basic DECIMAL(12,2) DEFAULT 0, hra DECIMAL(12,2) DEFAULT 0, allowance DECIMAL(12,2) DEFAULT 0, gross DECIMAL(12,2) DEFAULT 0, pf DECIMAL(12,2) DEFAULT 0, esi DECIMAL(12,2) DEFAULT 0, professional_tax DECIMAL(12,2) DEFAULT 0, total_deductions DECIMAL(12,2) DEFAULT 0, net_pay DECIMAL(12,2) DEFAULT 0, generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_hr_payslip (payroll_run_id,employee_id), FOREIGN KEY (payroll_run_id) REFERENCES hr_payroll_runs(id) ON DELETE CASCADE, FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS hr_settings (company_id INT PRIMARY KEY, payslip_footer VARCHAR(255) DEFAULT 'This is a computer-generated payslip and does not require a signature.', leave_policy TEXT, payslip_template MEDIUMTEXT, FOREIGN KEY (company_id) REFERENCES hr_companies(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    foreach ($sql as $statement) { $db->query($statement); }
}
hrEnsureSchema($hrDb);

function hrEnsurePayslipTemplateColumn(mysqli $db): void {
    $result = $db->query('SHOW COLUMNS FROM hr_settings');
    $existing = [];
    while ($row = $result ? $result->fetch_assoc() : null) $existing[] = $row['Field'];
    if (!in_array('payslip_template', $existing, true)) {
        $db->query('ALTER TABLE hr_settings ADD COLUMN payslip_template MEDIUMTEXT NULL');
    }
}
hrEnsurePayslipTemplateColumn($hrDb);

function hrEnsureMenuAccessSchema(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS hr_menu_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        menu_key VARCHAR(80) NOT NULL,
        is_allowed TINYINT(1) NOT NULL DEFAULT 0,
        UNIQUE KEY uq_hr_menu_permission (company_id, employee_id, menu_key),
        FOREIGN KEY (company_id) REFERENCES hr_companies(id) ON DELETE CASCADE,
        FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
hrEnsureMenuAccessSchema($hrDb);

function hrEnsureHolidayStateColumns(mysqli $db): void {
    $result = $db->query('SHOW COLUMNS FROM hr_holidays');
    $existing = [];
    while ($row = $result ? $result->fetch_assoc() : null) { $existing[] = $row['Field']; }
    if (!in_array('state_name', $existing, true)) {
        $db->query('ALTER TABLE hr_holidays ADD COLUMN state_name VARCHAR(80) NOT NULL DEFAULT ""');
    }
}
hrEnsureHolidayStateColumns($hrDb);

function hrSeedStateMaster(mysqli $db): void {
    $count = (int)($db->query('SELECT COUNT(*) AS n FROM hr_states')->fetch_assoc()['n'] ?? 0);
    if ($count > 0) {
        return;
    }

    $states = [
        ['Andhra Pradesh','AP'],['Arunachal Pradesh','AR'],['Assam','AS'],['Bihar','BR'],['Chhattisgarh','CG'],
        ['Goa','GA'],['Gujarat','GJ'],['Haryana','HR'],['Himachal Pradesh','HP'],['Jharkhand','JH'],
        ['Karnataka','KA'],['Kerala','KL'],['Madhya Pradesh','MP'],['Maharashtra','MH'],['Manipur','MN'],
        ['Meghalaya','ML'],['Mizoram','MZ'],['Nagaland','NL'],['Odisha','OD'],['Punjab','PB'],
        ['Rajasthan','RJ'],['Sikkim','SK'],['Tamil Nadu','TN'],['Telangana','TS'],['Tripura','TR'],
        ['Uttar Pradesh','UP'],['Uttarakhand','UK'],['West Bengal','WB'],['Andaman and Nicobar Islands','AN'],
        ['Chandigarh','CH'],['Dadra and Nagar Haveli and Daman and Diu','DN'],['Delhi','DL'],['Jammu and Kashmir','JK'],
        ['Ladakh','LA'],['Lakshadweep','LD'],['Puducherry','PY']
    ];

    $stmt = $db->prepare('INSERT INTO hr_states (state_name, state_code) VALUES (?, ?)');
    foreach ($states as [$name, $code]) {
        $stmt->bind_param('ss', $name, $code);
        $stmt->execute();
    }
    $stmt->close();
}
hrSeedStateMaster($hrDb);

function hrEnsureCompanyBrandColumns(mysqli $db): void {
    $fields = [
        ['logo_width', 'INT UNSIGNED NOT NULL DEFAULT 180'],
        ['logo_height', 'INT UNSIGNED NOT NULL DEFAULT 52'],
        ['logo_fit', 'VARCHAR(20) NOT NULL DEFAULT "contain"']
    ];
    $result = $db->query('SHOW COLUMNS FROM hr_companies');
    $existing = [];
    while ($row = $result ? $result->fetch_assoc() : null) { $existing[] = $row['Field']; }
    foreach ($fields as [$name, $type]) {
        if (!in_array($name, $existing, true)) {
            $db->query('ALTER TABLE hr_companies ADD COLUMN '.$name.' '.$type);
        }
    }
}
hrEnsureCompanyBrandColumns($hrDb);

function hrEnsureEmployeeAuthColumns(mysqli $db): void {
    $fields = [
        ['password_hash', 'VARCHAR(255) NOT NULL DEFAULT ""'],
        ['role', 'VARCHAR(30) NOT NULL DEFAULT "Employee"'],
        ['device_employee_id', 'VARCHAR(50) NOT NULL DEFAULT ""'],
        ['employee_pin', 'VARCHAR(12) NOT NULL DEFAULT ""'],
        ['last_login_at', 'TIMESTAMP NULL DEFAULT NULL']
    ];
    $result = $db->query('SHOW COLUMNS FROM hr_employees');
    $existing = [];
    while ($row = $result ? $result->fetch_assoc() : null) { $existing[] = $row['Field']; }
    foreach ($fields as [$name, $type]) {
        if (!in_array($name, $existing, true)) {
            $db->query('ALTER TABLE hr_employees ADD COLUMN '.$name.' '.$type);
        }
    }
}
hrEnsureEmployeeAuthColumns($hrDb);

function hrEnsureEmployeeProfileColumns(mysqli $db): void {
    $fields = [
        ['dob', 'DATE DEFAULT NULL'],
        ['gender', 'VARCHAR(20) DEFAULT ""'],
        ['address', 'TEXT'],
        ['city', 'VARCHAR(80) DEFAULT ""'],
        ['state', 'VARCHAR(80) DEFAULT ""'],
        ['country', 'VARCHAR(80) DEFAULT ""'],
        ['qualification', 'VARCHAR(150) DEFAULT ""'],
        ['experience_years', 'DECIMAL(4,1) DEFAULT 0'],
        ['emergency_contact_name', 'VARCHAR(120) DEFAULT ""'],
        ['emergency_contact_phone', 'VARCHAR(30) DEFAULT ""'],
        ['aadhar', 'VARCHAR(20) DEFAULT ""'],
        ['passport', 'VARCHAR(30) DEFAULT ""'],
        ['uan', 'VARCHAR(30) DEFAULT ""'],
        ['esi_no', 'VARCHAR(30) DEFAULT ""']
    ];
    $result = $db->query('SHOW COLUMNS FROM hr_employees');
    $existing = [];
    while ($row = $result ? $result->fetch_assoc() : null) { $existing[] = $row['Field']; }
    foreach ($fields as [$name, $type]) {
        if (!in_array($name, $existing, true)) {
            $db->query('ALTER TABLE hr_employees ADD COLUMN '.$name.' '.$type);
        }
    }
}
hrEnsureEmployeeProfileColumns($hrDb);

function hrEnsureAttendanceTrackingTables(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS hr_attendance_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        log_date DATE NOT NULL,
        punch_type ENUM('check_in','check_out') NOT NULL,
        punch_time TIME NOT NULL,
        source VARCHAR(30) DEFAULT 'manual',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_hr_attendance_logs_employee_date (employee_id,log_date),
        KEY idx_hr_attendance_logs_company_date (company_id,log_date),
        FOREIGN KEY (company_id) REFERENCES hr_companies(id) ON DELETE CASCADE,
        FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS hr_weekly_offs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        employee_id INT NULL,
        state_name VARCHAR(80) DEFAULT '',
        day_of_week ENUM('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_hr_weekly_off (company_id, employee_id, state_name, day_of_week),
        KEY idx_hr_weekly_off_company (company_id),
        FOREIGN KEY (company_id) REFERENCES hr_companies(id) ON DELETE CASCADE,
        FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
hrEnsureAttendanceTrackingTables($hrDb);

function hrSeedDummyHolidays(mysqli $db, int $companyId): void {
    $count = (int)($db->query("SELECT COUNT(*) AS n FROM hr_holidays WHERE company_id=$companyId")->fetch_assoc()['n'] ?? 0);
    if ($count > 0) {
        return;
    }

    $items = [
        ['West Bengal', 'New Year Day', '2026-01-01', 1, 'Annual holiday'],
        ['Maharashtra', 'Republic Day', '2026-01-26', 1, 'National holiday'],
        ['Tamil Nadu', 'Pongal', '2026-01-14', 1, 'Regional harvest festival'],
        ['West Bengal', 'International Mother Language Day', '2026-02-21', 1, 'Regional observance'],
        ['Karnataka', 'Maha Shivaratri', '2026-02-15', 1, 'Regional festival'],
        ['India', 'Holi', '2026-03-03', 1, 'Festival of colors'],
        ['Maharashtra', 'Gudi Padwa', '2026-03-19', 1, 'Regional new year'],
        ['India', 'Good Friday', '2026-04-03', 1, 'Christian holiday'],
        ['India', 'Labour Day', '2026-05-01', 1, 'Labour holiday'],
        ['India', 'Independence Day', '2026-08-15', 1, 'National holiday'],
        ['West Bengal', 'Durga Puja', '2026-10-20', 1, 'Major festival'],
        ['Tamil Nadu', 'Deepavali', '2026-11-08', 1, 'Festival of lights'],
        ['India', 'Christmas', '2026-12-25', 1, 'Christmas holiday']
    ];

    $stmt = $db->prepare('INSERT INTO hr_holidays (company_id, state_name, holiday_name, holiday_date, repeat_annually, description) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($items as $item) {
        [$state, $name, $date, $repeat, $desc] = $item;
        $stmt->bind_param('isssis', $companyId, $state, $name, $date, $repeat, $desc);
        $stmt->execute();
    }
    $stmt->close();
}

function hrSeedDefaultWeeklyOffs(mysqli $db, int $companyId): void {
    $scheduleCount = (int)($db->query("SELECT COUNT(*) AS n FROM hr_company_work_schedule WHERE company_id = $companyId")->fetch_assoc()['n'] ?? 0);
    if ($scheduleCount > 0) {
        return;
    }

    $days = ['Saturday', 'Sunday'];
    foreach ($days as $day) {
        $defaultExists = $db->query("SELECT id FROM hr_weekly_offs WHERE company_id = $companyId AND employee_id IS NULL AND COALESCE(state_name, '') = '' AND day_of_week = '$day' LIMIT 1")->fetch_assoc();
        if ($defaultExists) {
            continue;
        }

        $db->query("INSERT INTO hr_weekly_offs (company_id, employee_id, state_name, day_of_week, is_active) VALUES ($companyId, NULL, '', '$day', 1)");
    }
}

function hrEnsureCompanyWorkSchedule(mysqli $db, int $companyId): void {
    $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    foreach ($days as $day) {
        $row = $db->query("SELECT id, is_working_day FROM hr_company_work_schedule WHERE company_id = $companyId AND day_of_week = '$day' LIMIT 1")->fetch_assoc();
        $dayMode = in_array($day, ['Saturday','Sunday'], true) ? 0 : 1;
        $start = '09:00:00';
        $end = '18:00:00';

        if ($row) {
            continue;
        }

        $db->query("INSERT INTO hr_company_work_schedule (company_id, day_of_week, is_working_day, office_start_time, office_end_time) VALUES ($companyId, '$day', $dayMode, '$start', '$end')");
    }
}

function hrGetCompanyWorkSchedule(mysqli $db, int $companyId): array {
    $result = $db->query("SELECT day_of_week, is_working_day, office_start_time, office_end_time FROM hr_company_work_schedule WHERE company_id = $companyId ORDER BY FIELD(day_of_week, 'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday')")->fetch_all(MYSQLI_ASSOC);
    $schedule = [];
    foreach ($result as $row) {
        $schedule[$row['day_of_week']] = [
            'is_working_day' => (int)$row['is_working_day'] === 1,
            'office_start_time' => $row['office_start_time'] ?: '09:00:00',
            'office_end_time' => $row['office_end_time'] ?: '18:00:00'
        ];
    }
    return $schedule;
}

function hrIsCompanyRestDay(mysqli $db, int $companyId, string $date): bool {
    $date = trim($date);
    if ($date === '') {
        return false;
    }
    $dayName = date('l', strtotime($date));
    $schedule = hrGetCompanyWorkSchedule($db, $companyId);
    $record = $schedule[$dayName] ?? ['is_working_day' => true, 'office_start_time' => '09:00:00', 'office_end_time' => '18:00:00'];
    return !$record['is_working_day'];
}

function hrSeedData(mysqli $db): void {
    $companyCount = (int)($db->query('SELECT COUNT(*) AS n FROM hr_companies')->fetch_assoc()['n'] ?? 0);
    $companyIds = $db->query('SELECT id FROM hr_companies')->fetch_all(MYSQLI_ASSOC);
    $allCompanyIds = [];
    foreach ($companyIds as $row) {
        $allCompanyIds[] = (int)$row['id'];
    }

    if ($companyCount === 0) {
        $db->query("INSERT INTO hr_companies (company_name,company_code,email,phone,address,primary_color) VALUES ('Acme Technologies Pvt Ltd','ACME','hr@acme.test','+91 33 4000 1000','Kolkata, West Bengal','#be185d')");
        $companyId = (int)$db->insert_id;
        $allCompanyIds[] = $companyId;
        $db->query("INSERT INTO hr_departments (company_id,name) VALUES ($companyId,'Engineering'),($companyId,'Product Design'),($companyId,'People Operations'),($companyId,'Marketing')");
    }

    foreach ($allCompanyIds as $companyId) {
        $employeeExists = $db->query("SELECT id FROM hr_employees WHERE company_id = $companyId AND employee_code = 'EMP-001' LIMIT 1")->fetch_assoc();
        if (!$employeeExists) {
            $hash = password_hash('123456', PASSWORD_DEFAULT);
            $db->query("INSERT INTO hr_employees (company_id, employee_code, first_name, last_name, email, department, designation, join_date, basic_salary, hra, allowance, role, password_hash) VALUES ($companyId, 'EMP-001', 'Sarah', 'Mitchell', 'sarah@acme.test', 'Product Design', 'Product Designer', CURDATE(), 72000, 18000, 8000, 'Employee', '$hash')");
        }

        $roleSeed = [
            ['HR-001','Amit','Sarkar','hr@acme.test','People Operations','HR Manager','HR'],
            ['MD-001','Rohan','Sen','md@acme.test','Executive','Managing Director','MD'],
            ['IT-001','Dev','Nair','it@acme.test','IT Operations','IT Manager','IT']
        ];
        foreach ($roleSeed as $row) {
            $code = strtoupper((string)$row[0]);
            $exists = $db->query("SELECT id FROM hr_employees WHERE company_id = $companyId AND employee_code = '$code' LIMIT 1")->fetch_assoc();
            if ($exists) {
                continue;
            }
            $hash = password_hash('123456', PASSWORD_DEFAULT);
            $db->query("INSERT INTO hr_employees (company_id,employee_code,first_name,last_name,email,department,designation,join_date,role,password_hash) VALUES ($companyId,'" . $db->real_escape_string($row[0]) . "','" . $db->real_escape_string($row[1]) . "','" . $db->real_escape_string($row[2]) . "','" . $db->real_escape_string($row[3]) . "','" . $db->real_escape_string($row[4]) . "','" . $db->real_escape_string($row[5]) . "',CURDATE(),'" . $db->real_escape_string($row[6]) . "','" . $db->real_escape_string($hash) . "')");
        }

        $duplicateCount = (int)($db->query("SELECT COUNT(*) AS n FROM hr_employees WHERE company_id = $companyId AND employee_code = 'EMP-001'")->fetch_assoc()['n'] ?? 0);
        if ($duplicateCount > 1) {
            $db->query("DELETE FROM hr_employees WHERE company_id = $companyId AND employee_code = 'EMP-001' AND id <> (SELECT id FROM (SELECT id FROM hr_employees WHERE company_id = $companyId AND employee_code = 'EMP-001' ORDER BY id LIMIT 1) AS x)");
        }

        $db->query("INSERT IGNORE INTO hr_settings (company_id) VALUES ($companyId)");
        hrSeedDummyHolidays($db, $companyId);
        hrSeedDefaultWeeklyOffs($db, $companyId);
        hrEnsureCompanyWorkSchedule($db, $companyId);
    }

    $db->query("UPDATE hr_employees SET role = 'Employee' WHERE role IS NULL OR role = ''");
    foreach ($allCompanyIds as $companyId) {
        hrSeedDummyHolidays($db, (int)$companyId);
        hrSeedDefaultWeeklyOffs($db, (int)$companyId);
        hrEnsureCompanyWorkSchedule($db, (int)$companyId);
    }
}
hrSeedData($hrDb);

function hrCompanies(mysqli $db): array { $q=$db->query("SELECT * FROM hr_companies WHERE status='Active' ORDER BY company_name"); return $q ? $q->fetch_all(MYSQLI_ASSOC) : []; }
function hrCompanyId(mysqli $db): int {
    $employeeRole = strtoupper((string)($_SESSION['hr_employee_role'] ?? ''));
    if ($employeeRole === 'EMPLOYEE' && !empty($_SESSION['hr_employee_company_id'])) {
        return (int)$_SESSION['hr_employee_company_id'];
    }
    $companies = hrCompanies($db);
    $id = (int)($_SESSION['hr_company_id'] ?? 0);
    foreach ($companies as $company) if ((int)$company['id'] === $id) return $id;
    $_SESSION['hr_company_id'] = (int)($companies[0]['id'] ?? 0);
    return (int)($_SESSION['hr_company_id'] ?? 0);
}
function hrCompany(mysqli $db): array { $id=hrCompanyId($db); $st=$db->prepare('SELECT * FROM hr_companies WHERE id=?'); $st->bind_param('i',$id); $st->execute(); $r=$st->get_result()->fetch_assoc() ?: []; $st->close(); return $r; }
function hrStateList(mysqli $db): array { $q=$db->query('SELECT * FROM hr_states ORDER BY state_name ASC'); return $q ? $q->fetch_all(MYSQLI_ASSOC) : []; }
function hrAttendanceSummaryForDate(mysqli $db, int $employeeId, string $date): array {
    $date = trim($date);
    if ($date === '' || $employeeId <= 0) {
        return ['check_in' => null, 'check_out' => null, 'total_minutes' => 0, 'total_hours' => '0:00'];
    }

    $row = $db->query("SELECT MIN(CASE WHEN punch_type = 'check_in' THEN punch_time END) AS first_in, MAX(CASE WHEN punch_type = 'check_out' THEN punch_time END) AS last_out FROM hr_attendance_logs WHERE employee_id = $employeeId AND log_date = '$date' ")->fetch_assoc();
    $checkIn = $row['first_in'] ?? null;
    $checkOut = $row['last_out'] ?? null;
    $totalMinutes = 0;
    if ($checkIn && $checkOut) {
        $checkInTs = strtotime($date . ' ' . $checkIn);
        $checkOutTs = strtotime($date . ' ' . $checkOut);
        if ($checkOutTs > $checkInTs) {
            $totalMinutes = (int)(($checkOutTs - $checkInTs) / 60);
        }
    }
    $hours = intdiv($totalMinutes, 60);
    $minutes = $totalMinutes % 60;
    return ['check_in' => $checkIn, 'check_out' => $checkOut, 'total_minutes' => $totalMinutes, 'total_hours' => sprintf('%d:%02d', $hours, $minutes)];
}
function hrIsWeeklyOffForEmployee(mysqli $db, int $companyId, int $employeeId, string $date): bool {
    $date = trim($date);
    if ($date === '') {
        return false;
    }
    $employee = $db->query("SELECT state FROM hr_employees WHERE id = $employeeId AND company_id = $companyId LIMIT 1")->fetch_assoc();
    $employeeState = trim((string)($employee['state'] ?? ''));
    $dayName = date('l', strtotime($date));
    if (hrIsCompanyRestDay($db, $companyId, $date)) {
        return true;
    }
    $rules = $db->query("SELECT employee_id, state_name, day_of_week FROM hr_weekly_offs WHERE company_id = $companyId AND is_active = 1")->fetch_all(MYSQLI_ASSOC);
    foreach ($rules as $rule) {
        if (($rule['day_of_week'] ?? '') !== $dayName) {
            continue;
        }
        $ruleEmployeeId = (int)($rule['employee_id'] ?? 0);
        $ruleState = trim((string)($rule['state_name'] ?? ''));

        if ($ruleEmployeeId > 0 && $ruleEmployeeId === $employeeId) {
            return true;
        }
        if ($ruleState !== '' && $ruleState === $employeeState) {
            return true;
        }
        if ($ruleEmployeeId === 0 && $ruleState === '') {
            return true;
        }
    }
    return false;
}
function hrEsc(mysqli $db, string $value): string { return $db->real_escape_string(trim($value)); }
function hrMoney(float $value): string { return 'Rs. '.number_format($value,2); }
function hrFlash(string $message, string $kind='success'): void { $_SESSION['hr_flash']=['message'=>$message,'kind'=>$kind]; }
function hrConsumeFlash(): ?array { $f=$_SESSION['hr_flash'] ?? null; unset($_SESSION['hr_flash']); return $f; }
function hrCsrf(): string { return generateCSRFToken(); }
function hrRequireCsrf(): void { if($_SERVER['REQUEST_METHOD']==='POST' && !validateCSRFToken($_POST['csrf'] ?? '')) { http_response_code(419); exit('Invalid request token. Please refresh and try again.'); } }
function hrCurrentPath(): string { return basename($_SERVER['PHP_SELF']); }
function hrEmployeeByCode(mysqli $db, string $code, ?string $role = null): ?array {
    $code = trim($code);
    if ($code === '') return null;

    $requestedRole = strtoupper(trim((string)($role ?? 'Employee')));
    $st = $db->prepare('SELECT * FROM hr_employees WHERE employee_code = ? AND LOWER(COALESCE(role, "Employee")) = LOWER(?) LIMIT 1');
    $st->bind_param('ss', $code, $requestedRole);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}
function hrEmployeeByAttendanceId(mysqli $db, string $identifier): ?array {
    $identifier = trim($identifier);
    if ($identifier === '') return null;

    $st = $db->prepare('SELECT * FROM hr_employees WHERE employee_code = ? OR device_employee_id = ? LIMIT 1');
    $st->bind_param('ss', $identifier, $identifier);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}
function hrEmployeeById(mysqli $db, int $employeeId): ?array {
    if ($employeeId <= 0) return null;
    $st = $db->prepare('SELECT * FROM hr_employees WHERE id = ? LIMIT 1');
    $st->bind_param('i', $employeeId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}
function hrAuthenticateEmployee(mysqli $db, string $employeeCode, string $password, ?string $role = null, ?int $companyId = null): ?array {
    $employee = null;
    if ($companyId && $companyId > 0) {
        $requestedRole = strtoupper(trim((string)($role ?? 'Employee')));
        $st = $db->prepare('SELECT * FROM hr_employees WHERE employee_code = ? AND company_id = ? AND LOWER(COALESCE(role, "Employee")) = LOWER(?) LIMIT 1');
        $st->bind_param('sis', $employeeCode, $companyId, $requestedRole);
        $st->execute();
        $employee = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
    } else {
        $employee = hrEmployeeByCode($db, $employeeCode, $role ?? 'Employee');
    }
    if (!$employee) {
        return null;
    }

    $storedHash = (string)($employee['password_hash'] ?? '');
    $legacyDefaultPassword = '123456';

    if ($storedHash === '') {
        if ($password !== $legacyDefaultPassword) {
            return null;
        }
        $storedHash = password_hash($legacyDefaultPassword, PASSWORD_DEFAULT);
        $db->query('UPDATE hr_employees SET password_hash = "' . $db->real_escape_string($storedHash) . '" WHERE id=' . (int)$employee['id']);
    }

    if (!password_verify($password, $storedHash)) {
        return null;
    }

    $_SESSION['hr_employee_id'] = (int)$employee['id'];
    $_SESSION['hr_employee_company_id'] = (int)$employee['company_id'];
    $_SESSION['hr_employee_role'] = strtoupper((string)($employee['role'] ?? 'Employee'));
    $db->query('UPDATE hr_employees SET last_login_at = NOW() WHERE id='.(int)$employee['id']);
    return $employee;
}
function hrClearEmployeeSession(): void {
    unset($_SESSION['hr_employee_id'], $_SESSION['hr_employee_company_id'], $_SESSION['hr_employee_role']);
}
function hrSetNoCacheHeaders(): void {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
function hrRequireEmployeeLogin(): void {
    if (!isset($_SESSION['hr_employee_id'])) {
        header('Location: employee_login.php');
        exit;
    }
}
function hrCurrentEmployee(mysqli $db): ?array {
    $employeeId = (int)($_SESSION['hr_employee_id'] ?? 0);
    if ($employeeId <= 0) return null;
    return hrEmployeeById($db, $employeeId);
}
function hrMenuItems(): array {
    return [
        'index.php' => ['layout-dashboard', 'Overview'],
        'ai.php' => ['sparkles', 'AI HR assistant'],
        'employees.php' => ['users', 'Employees'],
        'employee_onboarding.php' => ['user-plus', 'Onboarding'],
        'employee_joining.php' => ['briefcase-business', 'Joining'],
        'employee_payroll.php' => ['wallet-cards', 'Payroll setup'],
        'employee_payslips.php' => ['file-text', 'My payslips'],
        'employee_leave.php' => ['calendar-plus', 'My leave'],
        'employee_assets.php' => ['laptop-2', 'IT assets'],
        'attendance.php' => ['calendar-check-2', 'Attendance'],
        'weekly_off.php' => ['calendar-x-2', 'Weekly off'],
        'leave.php' => ['calendar-days', 'Leave requests'],
        'holiday.php' => ['calendar-range', 'Holiday setup'],
        'payroll.php' => ['calculator', 'Monthly payroll'],
        'import.php' => ['file-spreadsheet', 'Excel import'],
        'company.php' => ['building-2', 'Company setup'],
        'formats.php' => ['sliders-horizontal', 'Brand & formats'],
        'admin.php' => ['shield-check', 'Company administrator']
    ];
}
function hrIsMenuAdmin(?array $employee): bool {
    return in_array(strtoupper((string)($employee['role'] ?? '')), ['IT', 'HR', 'MD'], true);
}
function hrEmployeeMenuAllowed(mysqli $db, int $companyId, int $employeeId, string $menuKey): bool {
    $st = $db->prepare('SELECT is_allowed FROM hr_menu_permissions WHERE company_id = ? AND employee_id = ? AND menu_key = ? LIMIT 1');
    $st->bind_param('iis', $companyId, $employeeId, $menuKey);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ? (bool)$row['is_allowed'] : false;
}
function hrAiInsights(mysqli $db, int $companyId): array {
    $today = date('Y-m-d');
    $count = (int)($db->query("SELECT COUNT(*) n FROM hr_employees WHERE company_id=$companyId AND status='Active'")->fetch_assoc()['n'] ?? 0);
    $present = (int)($db->query("SELECT COUNT(*) n FROM hr_attendance WHERE company_id=$companyId AND attendance_date='$today' AND status IN ('Present','Late','Paid Leave','Work From Home')")->fetch_assoc()['n'] ?? 0);
    $pending = (int)($db->query("SELECT COUNT(*) n FROM hr_leave_requests WHERE company_id=$companyId AND status='Pending'")->fetch_assoc()['n'] ?? 0);
    $attendanceRate = $count ? round(($present / $count) * 100) : 0;
    $payrollRunCount = (int)($db->query("SELECT COUNT(*) n FROM hr_payroll_runs WHERE company_id=$companyId AND payroll_month='".date('Y-m')."'")->fetch_assoc()['n'] ?? 0);
    $insights = [];
    if ($pending > 0) {
        $insights[] = ['title' => 'Leave approvals need review', 'message' => 'There are '.$pending.' pending leave requests. Review time-off actions before the next roster or staffing decision.', 'link' => 'leave.php', 'action' => 'Review leave'];
    }
    if ($attendanceRate < 80) {
        $insights[] = ['title' => 'Attendance dip detected', 'message' => 'Today\'s attendance is at '.$attendanceRate.'%, below the healthy 80% benchmark. Follow up on absent employees and check late entries.', 'link' => 'attendance.php', 'action' => 'Check attendance'];
    }
    if ($payrollRunCount === 0) {
        $insights[] = ['title' => 'Payroll is still pending', 'message' => 'The payroll for '.date('F Y').' has not been finalized yet. Run salary processing to keep payouts on schedule.', 'link' => 'payroll.php', 'action' => 'Run payroll'];
    }
    if ($count > 0 && $attendanceRate >= 90) {
        $insights[] = ['title' => 'Strong workforce health', 'message' => 'Your team is tracking at '.$attendanceRate.'% attendance. This is a strong signal for retention and operational stability.', 'link' => 'employees.php', 'action' => 'View team'];
    }
    if (!$insights) {
        $insights[] = ['title' => 'Everything looks healthy', 'message' => 'No urgent HR issues are detected right now. Continue onboarding, performance follow-ups, and policy reviews.', 'link' => 'index.php', 'action' => 'Overview'];
    }
    return array_slice($insights, 0, 3);
}

function hrFilterEmployeeNavigation(string $html): string {
    global $hrDb;
    $employeeRole = strtoupper((string)($_SESSION['hr_employee_role'] ?? ''));
    $employee = hrCurrentEmployee($hrDb);
    if (hrIsMenuAdmin($employee)) {
        $adminLink = strtoupper((string)($employee['role'] ?? '')) === 'IT' ? '<a href="admin.php"><i data-lucide="shield-check"></i>Company administrator</a>' : '';
        $html = preg_replace('/(<nav>)(.*?)(<\/nav>)/s', '$1$2' . $adminLink . '<a href="menu_access.php"><i data-lucide="key-round"></i>Menu access</a>$3', $html, 1);
        return $html;
    }

    if ($employeeRole !== 'EMPLOYEE' || !$employee) {
        return $html;
    }

    $links = '<a href="employee_dashboard.php"><i data-lucide="layout-dashboard"></i>My dashboard</a>';
    $links .= '<a href="employee_payslips.php"><i data-lucide="file-text"></i>My payslips</a>';
    $links .= '<a href="employee_leave.php"><i data-lucide="calendar-plus"></i>My leave</a>';
    $html = preg_replace('/<form method="post" action="switch_company\.php" class="company-switch">.*?<\/form>/s', '', $html);
    $html = preg_replace('/<p class="section-label">WORKSPACE<\/p>.*?<nav>.*?<\/nav>/s', '<p class="section-label">MY WORKSPACE</p><nav>' . $links . '</nav>', $html);
    return $html;
}
ob_start('hrFilterEmployeeNavigation');

function hrPageStart(string $title, bool $showSidebar = true): void { global $hrDb; $company=hrCompany($hrDb); $companies=hrCompanies($hrDb); $flash=hrConsumeFlash(); $path=hrCurrentPath(); $brandWidth = max(32, (int)($company['logo_width'] ?? 180)); $brandHeight = max(24, (int)($company['logo_height'] ?? 52)); $brandFit = htmlspecialchars((string)($company['logo_fit'] ?? 'contain')); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=htmlspecialchars($title)?> | HRCloud AI HRMS</title><link rel="stylesheet" href="saas.css"><link rel="stylesheet" href="ai.css"><script src="https://unpkg.com/lucide@latest"></script></head><body style="--brand:<?=htmlspecialchars($company['primary_color'])?>"><div class="shell"><?php if ($showSidebar): ?><aside class="side"><a class="logo" href="index.php"><?php if (!empty($company['logo_url'])): ?><span class="brand-logo"><img src="<?=htmlspecialchars($company['logo_url'])?>" alt="Company logo" style="width:100%;height:100%;object-fit:<?= $brandFit ?>;display:block;border-radius:12px;"></span><?php else: ?><span class="brand-mark"><?=htmlspecialchars(substr($company['company_name'] ?? 'HR', 0, 2) ?: 'HR')?></span><?php endif; ?><span class="brand-text"><?=htmlspecialchars($company['company_name'] ?? 'HRCloud AI')?></span></a><form method="post" action="switch_company.php" class="company-switch"><input type="hidden" name="csrf" value="<?=hrCsrf()?>"><select name="company_id" onchange="this.form.submit()"><?php foreach($companies as $c): ?><option value="<?=$c['id']?>" <?=$c['id']==$company['id']?'selected':''?>><?=htmlspecialchars($c['company_name'])?></option><?php endforeach ?></select></form><p class="section-label">WORKSPACE</p><nav><?php $items=['index.php'=>['LayoutDashboard','Overview'],'ai.php'=>['Sparkles','AI HR assistant'],'employees.php'=>['Users','Employees'],'employee_onboarding.php'=>['UserPlus','Onboarding'],'employee_joining.php'=>['BriefcaseBusiness','Joining'],'employee_payroll.php'=>['WalletCards','Payroll setup'],'employee_assets.php'=>['Laptop2','IT assets'],'attendance.php'=>['CalendarCheck2','Attendance'],'weekly_off.php'=>['CalendarX2','Weekly off'],'leave.php'=>['CalendarDays','Leave requests'],'holiday.php'=>['CalendarRange','Holiday setup'],'payroll.php'=>['Calculator','Monthly payroll'],'import.php'=>['FileSpreadsheet','Excel import'],'company.php'=>['Building2','Company setup'],'formats.php'=>['SlidersHorizontal','Brand & formats']]; foreach($items as $file=>$meta): ?><a class="<?= $path===$file?'active':'' ?>" href="<?=$file?>"><i data-lucide="<?=strtolower($meta[0])?>"></i><?=htmlspecialchars($meta[1])?></a><?php endforeach ?></nav><div class="side-foot"><small>AI-enabled HR SaaS</small><span><?=htmlspecialchars($company['company_code'])?></span></div></aside><?php endif; ?><main <?php if(!$showSidebar): ?>style="width:100%;"<?php endif; ?>><header><?php if ($showSidebar): ?><button class="menu-btn" id="openMenu"><i data-lucide="menu"></i></button><?php endif; ?><div><h1><?=htmlspecialchars($title)?></h1><p><?=htmlspecialchars($company['company_name'])?></p></div><div class="header-user">
    <button class="circle"><i data-lucide="bell"></i></button>
    <?php $currentEmployee = hrCurrentEmployee($hrDb); ?>
    <div class="user-profile">
        <div class="user-initial"><?= htmlspecialchars(substr(($currentEmployee['first_name'] ?? 'HR'), 0, 1) . substr(($currentEmployee['last_name'] ?? 'User'), 0, 1)) ?></div>
        <div class="user-meta">
            <strong><?= htmlspecialchars(($currentEmployee['first_name'] ?? 'HR') . ' ' . ($currentEmployee['last_name'] ?? 'User')) ?></strong>
            <small><?= htmlspecialchars(strtoupper((string)($currentEmployee['role'] ?? 'Employee'))) ?></small>
        </div>
        <a href="logout_employee.php" class="header-logout">Logout</a>
    </div>
</div></header><section class="content"><?php if($flash): ?><div class="flash <?=$flash['kind']?>"><?=htmlspecialchars($flash['message'])?></div><?php endif; ?>
<?php }
function hrPageEnd(): void { ?></section></main></div><script>lucide.createIcons();const menuBtn=document.querySelector('#openMenu');if(menuBtn){menuBtn.addEventListener('click',()=>document.querySelector('.side')?.classList.toggle('open'));}</script></body></html><?php }

