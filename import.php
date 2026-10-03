<?php
require_once __DIR__ . '/core.php';
hrRequireCsrf();

$companyId = hrCompanyId($hrDb);
$importMode = $_POST['import_type'] ?? 'employees';
$attendanceMonth = $_POST['attendance_month'] ?? date('Y-m');
$report = null;

function hrNormalizeHeader(string $value): string {
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim((string)$value, '_');
}

function hrAttendanceIdentifier(array $row): string {
    $knownKeys = ['employee_code', 'employee_id', 'employee_no', 'employee_number', 'emp_code', 'emp_id', 'emp_no', 'enroll_no', 'enroll_id', 'enrollment_no', 'en_no', 'enno', 'enroll', 'pin', 'pin_no', 'user_id', 'device_user_id', 'biometric_id', 'person_id', 'staff_id', 'code', 'id'];
    foreach ($knownKeys as $key) {
        if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
            return trim((string)$row[$key]);
        }
    }

    foreach ($row as $key => $value) {
        $key = strtolower((string)$key);
        if (preg_match('/(^|_)(emp|employee|en|enroll|pin|user|device|bio|biometric|person|staff|code|id)(_|$)/', $key) && trim((string)$value) !== '') {
            return trim((string)$value);
        }
    }

    return '';
}

function hrXlsxRows(string $file): array {
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) {
        throw new RuntimeException('Excel file could not be read.');
    }

    $shared = [];
    $sharedStrings = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedStrings) {
        $xml = simplexml_load_string($sharedStrings);
        if ($xml) {
            foreach ($xml->si as $si) {
                $shared[] = (string)($si->t ?? implode('', array_map('strval', (array)$si->r)));
            }
        }
    }

    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$sheet) {
        throw new RuntimeException('Sheet1 was not found.');
    }

    $xml = simplexml_load_string($sheet);
    if (!$xml) {
        throw new RuntimeException('The uploaded Excel file could not be parsed.');
    }

    $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $rows = [];
    foreach ($xml->xpath('//x:sheetData/x:row') as $row) {
        $data = [];
        foreach ($row->c as $cell) {
            preg_match('/([A-Z]+)/', (string)$cell['r'], $matches);
            $col = 0;
            foreach (str_split($matches[1] ?? 'A') as $letter) {
                $col = $col * 26 + ord($letter) - 64;
            }

            $value = (string)$cell->v;
            if ((string)$cell['t'] === 's') {
                $value = $shared[(int)$value] ?? '';
            }
            $data[$col] = $value;
        }

        ksort($data);
        $rows[] = array_values($data);
    }

    return $rows;
}

function hrXlsRows(string $file): array {
    if (!class_exists('COM')) {
        throw new RuntimeException('This server cannot read legacy .xls files because PHP COM is disabled. Open the report in Excel, choose Save As, and save it as .xlsx or CSV, then upload the converted file.');
    }

    $excel = new COM('Excel.Application');
    $excel->Visible = false;
    $excel->DisplayAlerts = false;
    $workbook = null;

    try {
        $workbook = $excel->Workbooks->Open(realpath($file), 0, true);
        $sheet = $workbook->Worksheets(1);
        $usedRange = $sheet->UsedRange;
        $values = $usedRange->Value;
        $rows = [];
        $rowCount = (int)$usedRange->Rows->Count;
        $columnCount = (int)$usedRange->Columns->Count;

        for ($rowIndex = 1; $rowIndex <= $rowCount; $rowIndex++) {
            $row = [];
            for ($columnIndex = 1; $columnIndex <= $columnCount; $columnIndex++) {
                $value = $rowCount === 1 && $columnCount === 1
                    ? $values
                    : $values[$rowIndex][$columnIndex];
                $row[] = is_scalar($value) ? (string)$value : '';
            }
            $rows[] = $row;
        }

        $workbook->Close(false);
        $excel->Quit();
        return $rows;
    } catch (Throwable $exception) {
        if ($workbook) {
            $workbook->Close(false);
        }
        $excel->Quit();
        throw new RuntimeException('The .xls report could not be read: ' . $exception->getMessage());
    }
}

function hrSheetRows(array $rows): array {
    if (!$rows) {
        return [];
    }

    $headerIndex = 0;
    foreach ($rows as $index => $candidate) {
        $candidateHeaders = array_map('hrNormalizeHeader', $candidate);
        $hasEmployeeField = (bool)array_intersect($candidateHeaders, ['employee_code', 'employee_id', 'employee_no', 'employee_number', 'emp_code', 'emp_id', 'emp_no', 'enroll_no', 'enroll_id', 'enrollment_no', 'en_no', 'enno', 'enroll', 'pin', 'pin_no', 'user_id', 'device_user_id', 'biometric_id', 'person_id', 'staff_id', 'code', 'id']);
        if (!$hasEmployeeField) {
            foreach ($candidateHeaders as $header) {
                if (preg_match('/(^|_)(emp|employee|en|enroll|pin|user|device|bio|biometric|person|staff|code|id)(_|$)/', $header)) {
                    $hasEmployeeField = true;
                    break;
                }
            }
        }
        $hasDateField = (bool)array_intersect($candidateHeaders, ['attendance_date', 'date', 'punch_date']);
        if ($hasEmployeeField && $hasDateField) {
            $headerIndex = $index;
            break;
        }
    }

    if ($headerIndex === 0 && count($rows) > 1) {
        foreach ($rows as $index => $candidate) {
            if ($index === 0) {
                continue;
            }
            $nonEmptyCells = count(array_filter($candidate, static fn($value): bool => trim((string)$value) !== ''));
            if ($nonEmptyCells >= 3) {
                $headerIndex = $index;
                break;
            }
        }
    }

    $headers = array_map('hrNormalizeHeader', $rows[$headerIndex]);
    $rows = array_slice($rows, $headerIndex + 1);
    $out = [];
    foreach ($rows as $row) {
        $row = array_pad($row, count($headers), '');
        $out[] = array_combine($headers, array_slice($row, 0, count($headers)));
    }

    return $out;
}

function hrNormalizeStatus(string $value): string {
    $map = [
        'p' => 'Present',
        'present' => 'Present',
        'late' => 'Late',
        'absent' => 'Absent',
        'a' => 'Absent',
        'wo' => 'Paid Leave',
        'wop' => 'Paid Leave',
        'vo' => 'Paid Leave',
        '½p' => 'Present',
        '1/2p' => 'Present',
        'paid leave' => 'Paid Leave',
        'paid_leave' => 'Paid Leave',
        'work from home' => 'Work From Home',
        'wfh' => 'Work From Home',
        'holiday' => 'Paid Leave',
        'leave' => 'Paid Leave'
    ];
    $key = strtolower(trim((string)$value));
    return $map[$key] ?? ucfirst(str_replace('_', ' ', $key));
}

function hrBasicWorkDurationRows(array $rows, string $month): array {
    $statusRow = $inRow = $outRow = null;
    $employeeCode = '';
    foreach ($rows as $row) {
        $label = strtolower(preg_replace('/[^a-z]/', '', trim((string)($row[0] ?? ''))));
        if (in_array($label, ['status', 'intime', 'outtime'], true)) {
            if ($label === 'status') $statusRow = $row;
            if ($label === 'intime') $inRow = $row;
            if ($label === 'outtime') $outRow = $row;
        }
        foreach ($row as $index => $value) {
            if (preg_match('/employee\s*id|emp\.?\s*id|emp\.?\s*code/i', (string)$value)) {
                $employeeCode = trim((string)($row[$index + 1] ?? ''));
            }
        }
    }

    if (!$statusRow || $employeeCode === '') {
        return [];
    }

    $rowsOut = [];
    $lastDay = (int)date('t', strtotime($month . '-01'));
    for ($column = 1; $column <= $lastDay; $column++) {
        $status = trim((string)($statusRow[$column] ?? ''));
        $checkIn = trim((string)($inRow[$column] ?? ''));
        $checkOut = trim((string)($outRow[$column] ?? ''));
        if ($status === '' && $checkIn === '' && $checkOut === '') continue;
        $rowsOut[] = [
            'employee_id' => $employeeCode,
            'attendance_date' => sprintf('%s-%02d', $month, $column),
            'status' => hrNormalizeStatus($status),
            'check_in' => $checkIn,
            'check_out' => $checkOut
        ];
    }
    return $rowsOut;
}

function hrNormalizeDate(string $value): string {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    if (is_numeric($value) && (float)$value > 25569) {
        return date('Y-m-d', strtotime('1899-12-30 +' . (int)$value . ' days'));
    }
    $value = str_replace('/', '-', $value);
    $ts = strtotime($value);
    return $ts !== false ? date('Y-m-d', $ts) : '';
}

function hrNormalizeTime(?string $value): ?string {
    $value = trim((string)($value ?? ''));
    if ($value === '') {
        return null;
    }

    if (is_numeric($value) && (float)$value >= 0 && (float)$value < 1) {
        $seconds = (int)round((float)$value * 86400);
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600) % 24, intdiv($seconds % 3600, 60), $seconds % 60);
    }

    if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value)) {
        $parts = explode(':', $value);
        $hours = (int)($parts[0] ?? 0);
        $minutes = (int)($parts[1] ?? 0);
        $seconds = (int)($parts[2] ?? 0);
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    $ts = strtotime($value);
    return $ts !== false ? date('H:i:s', $ts) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_FILES['sheet']['tmp_name'])) {
            throw new RuntimeException('Choose a CSV, XLS, or XLSX file first.');
        }

        $ext = strtolower(pathinfo($_FILES['sheet']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xls', 'xlsx'], true)) {
            throw new RuntimeException('Only .csv, .xls, and .xlsx files are supported.');
        }

        $importMode = $_POST['import_type'] ?? 'employees';
        $raw = $ext === 'csv'
            ? array_map('str_getcsv', file($_FILES['sheet']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            : ($ext === 'xls' ? hrXlsRows($_FILES['sheet']['tmp_name']) : hrXlsxRows($_FILES['sheet']['tmp_name']));

        if (!$raw) {
            throw new RuntimeException('The uploaded file is empty or contains no rows.');
        }

        $rows = hrSheetRows($raw);
                if ($importMode === 'attendance') {
                    $attendanceMonth = $_POST['attendance_month'] ?? date('Y-m');
                    $matrixRows = hrBasicWorkDurationRows($raw, $attendanceMonth);
                    if ($matrixRows) {
                        $rows = $matrixRows;
                    }
                }
        if (!$rows) {
            throw new RuntimeException('The uploaded file has no usable data rows.');
        }

        $report = ['created' => 0, 'updated' => 0, 'errors' => []];

        if ($importMode === 'attendance') {
            $previousEmployeeCode = '';
            foreach ($rows as $index => $row) {
                $employeeCode = hrAttendanceIdentifier($row);
                if ($employeeCode === '') {
                    $employeeCode = $previousEmployeeCode;
                } else {
                    $previousEmployeeCode = $employeeCode;
                }
                if ($employeeCode === '') {
                    $availableColumns = implode(', ', array_keys($row));
                    $report['errors'][] = 'Row ' . ($index + 2) . ': employee ID is required. Detected columns: ' . ($availableColumns ?: 'none') . '.';
                    continue;
                }

                $employee = hrEmployeeByAttendanceId($hrDb, $employeeCode);
                if (!$employee) {
                    $report['errors'][] = 'Row ' . ($index + 2) . ': employee code ' . htmlspecialchars($employeeCode) . ' was not found.';
                    continue;
                }

                $attendanceDate = hrNormalizeDate((string)($row['attendance_date'] ?? $row['date'] ?? $row['punch_date'] ?? ''));
                if ($attendanceDate === '') {
                    $report['errors'][] = 'Row ' . ($index + 2) . ': attendance_date is required.';
                    continue;
                }

                $status = hrNormalizeStatus((string)($row['status'] ?? $row['attendance_status'] ?? 'Present'));
                $checkIn = hrNormalizeTime((string)($row['check_in'] ?? $row['in_time'] ?? $row['first_in'] ?? ''));
                $checkOut = hrNormalizeTime((string)($row['check_out'] ?? $row['out_time'] ?? $row['last_out'] ?? ''));

                $checkInSql = $checkIn === null ? 'NULL' : "'" . $hrDb->real_escape_string($checkIn) . "'";
                $checkOutSql = $checkOut === null ? 'NULL' : "'" . $hrDb->real_escape_string($checkOut) . "'";
                $sql = "INSERT INTO hr_attendance (company_id, employee_id, attendance_date, status, check_in, check_out)
                        VALUES (
                            " . (int)$companyId . ",
                            " . (int)$employee['id'] . ",
                            '" . $hrDb->real_escape_string($attendanceDate) . "',
                            '" . $hrDb->real_escape_string($status) . "',
                            $checkInSql,
                            $checkOutSql
                        )
                        ON DUPLICATE KEY UPDATE
                            status = VALUES(status),
                            check_in = COALESCE(VALUES(check_in), check_in),
                            check_out = COALESCE(VALUES(check_out), check_out)";

                if ($hrDb->query($sql)) {
                    $report['updated']++;
                } else {
                    $report['errors'][] = 'Row ' . ($index + 2) . ': could not save attendance.';
                }
            }
        } else {
            foreach ($rows as $index => $row) {
                $code = trim((string)($row['employee_code'] ?? ''));
                $first = trim((string)($row['first_name'] ?? ''));
                if ($code === '' || $first === '') {
                    $report['errors'][] = 'Row ' . ($index + 2) . ': employee_code and first_name are required.';
                    continue;
                }

                $last = trim((string)($row['last_name'] ?? ''));
                $email = trim((string)($row['email'] ?? ''));
                $department = trim((string)($row['department'] ?? ''));
                $designation = trim((string)($row['designation'] ?? ''));
                $joinDate = hrNormalizeDate((string)($row['join_date'] ?? '')) ?: date('Y-m-d');
                $basic = (float)($row['basic_salary'] ?? 0);
                $hra = (float)($row['hra'] ?? 0);
                $allowance = (float)($row['allowance'] ?? 0);
                $role = strtoupper(trim((string)($row['role'] ?? 'Employee')));
                $passwordHash = password_hash('123456', PASSWORD_DEFAULT);
                $dob = hrNormalizeDate((string)($row['dob'] ?? ''));
                $gender = trim((string)($row['gender'] ?? ''));
                $address = trim((string)($row['address'] ?? ''));
                $city = trim((string)($row['city'] ?? ''));
                $state = trim((string)($row['state'] ?? ''));
                $country = trim((string)($row['country'] ?? 'India'));
                $qualification = trim((string)($row['qualification'] ?? ''));
                $experienceYear = (float)($row['experience_years'] ?? 0);
                $emergencyName = trim((string)($row['emergency_contact_name'] ?? ''));
                $emergencyPhone = trim((string)($row['emergency_contact_phone'] ?? ''));
                $aadhar = trim((string)($row['aadhar'] ?? ''));
                $passport = trim((string)($row['passport'] ?? ''));

                $sql = "INSERT INTO hr_employees (
                            company_id, employee_code, first_name, last_name, email, department, designation, join_date, basic_salary, hra, allowance, role, password_hash, dob, gender, address, city, state, country, qualification, experience_years, emergency_contact_name, emergency_contact_phone, aadhar, passport
                        ) VALUES (
                            " . (int)$companyId . ",
                            '" . $hrDb->real_escape_string($code) . "',
                            '" . $hrDb->real_escape_string($first) . "',
                            '" . $hrDb->real_escape_string($last) . "',
                            '" . $hrDb->real_escape_string($email) . "',
                            '" . $hrDb->real_escape_string($department) . "',
                            '" . $hrDb->real_escape_string($designation) . "',
                            '" . $hrDb->real_escape_string($joinDate) . "',
                            " . (float)$basic . ",
                            " . (float)$hra . ",
                            " . (float)$allowance . ",
                            '" . $hrDb->real_escape_string($role) . "',
                            '" . $hrDb->real_escape_string($passwordHash) . "',
                            '" . $hrDb->real_escape_string($dob ?: '') . "',
                            '" . $hrDb->real_escape_string($gender) . "',
                            '" . $hrDb->real_escape_string($address) . "',
                            '" . $hrDb->real_escape_string($city) . "',
                            '" . $hrDb->real_escape_string($state) . "',
                            '" . $hrDb->real_escape_string($country) . "',
                            '" . $hrDb->real_escape_string($qualification) . "',
                            " . (float)$experienceYear . ",
                            '" . $hrDb->real_escape_string($emergencyName) . "',
                            '" . $hrDb->real_escape_string($emergencyPhone) . "',
                            '" . $hrDb->real_escape_string($aadhar) . "',
                            '" . $hrDb->real_escape_string($passport) . "'
                        )
                        ON DUPLICATE KEY UPDATE
                            first_name = VALUES(first_name),
                            last_name = VALUES(last_name),
                            email = VALUES(email),
                            department = VALUES(department),
                            designation = VALUES(designation),
                            join_date = VALUES(join_date),
                            basic_salary = VALUES(basic_salary),
                            hra = VALUES(hra),
                            allowance = VALUES(allowance),
                            role = VALUES(role),
                            password_hash = VALUES(password_hash),
                            dob = VALUES(dob),
                            gender = VALUES(gender),
                            address = VALUES(address),
                            city = VALUES(city),
                            state = VALUES(state),
                            country = VALUES(country),
                            qualification = VALUES(qualification),
                            experience_years = VALUES(experience_years),
                            emergency_contact_name = VALUES(emergency_contact_name),
                            emergency_contact_phone = VALUES(emergency_contact_phone),
                            aadhar = VALUES(aadhar),
                            passport = VALUES(passport)";

                if ($hrDb->query($sql)) {
                    $insertedId = $hrDb->insert_id ?: $hrDb->query("SELECT id FROM hr_employees WHERE company_id=$companyId AND employee_code='" . $hrDb->real_escape_string($code) . "' LIMIT 1")->fetch_assoc()['id'] ?? 0;
                    if ($insertedId) {
                        $payCycle = trim((string)($row['pay_cycle'] ?? 'Monthly'));
                        $gross = (float)($row['gross_salary'] ?? $basic);
                        $bankName = trim((string)($row['bank_name'] ?? ''));
                        $bankAccount = trim((string)($row['bank_account'] ?? ''));
                        $notes = trim((string)($row['declaration_notes'] ?? ''));
                        $assetType = trim((string)($row['asset_type'] ?? ''));
                        $assetName = trim((string)($row['asset_name'] ?? ''));
                        $assetSerial = trim((string)($row['asset_serial_number'] ?? ''));
                        $assetDate = hrNormalizeDate((string)($row['asset_assigned_date'] ?? '')) ?: date('Y-m-d');
                        $hrDb->query("INSERT INTO hr_employee_payroll_setup (company_id, employee_id, pay_cycle, gross_salary, net_salary, effective_date, bank_name, bank_account, notes) VALUES ($companyId, $insertedId, '" . $hrDb->real_escape_string($payCycle) . "', " . $gross . ", " . $gross . ", '" . $hrDb->real_escape_string($joinDate) . "', '" . $hrDb->real_escape_string($bankName) . "', '" . $hrDb->real_escape_string($bankAccount) . "', '" . $hrDb->real_escape_string($notes) . "') ON DUPLICATE KEY UPDATE pay_cycle=VALUES(pay_cycle), gross_salary=VALUES(gross_salary), net_salary=VALUES(net_salary), effective_date=VALUES(effective_date), bank_name=VALUES(bank_name), bank_account=VALUES(bank_account), notes=VALUES(notes)");
                        if ($assetName !== '') {
                            $hrDb->query("INSERT INTO hr_employee_assets (company_id, employee_id, asset_type, asset_name, serial_number, assigned_date, status, issued_by, notes) VALUES ($companyId, $insertedId, '" . $hrDb->real_escape_string($assetType) . "', '" . $hrDb->real_escape_string($assetName) . "', '" . $hrDb->real_escape_string($assetSerial) . "', '" . $hrDb->real_escape_string($assetDate) . "', 'Assigned', 'CSV Import', '" . $hrDb->real_escape_string($notes) . "')");
                        }
                    }
                    $report['created']++;
                } else {
                    $report['errors'][] = 'Row ' . ($index + 2) . ': duplicate employee code or invalid data.';
                }
            }
        }
    } catch (Throwable $e) {
        $report = ['created' => 0, 'updated' => 0, 'errors' => [$e->getMessage()]];
    }
}

hrPageStart('Excel import');
?>
<div class="page-head">
    <div>
        <h2>Bulk import</h2>
        <p>Upload employee or attendance data for the selected company.</p>
    </div>
    <a class="button secondary" href="<?= $importMode === 'attendance' ? 'attendance_import_template.csv' : 'employee_import_template.csv' ?>" download>
        <i data-lucide="download"></i>Download template
    </a>
</div>

<div class="panel">
    <div class="notice">
        <b>Import mode:</b>
        <select name="import_type" form="import-form">
            <option value="employees" <?= $importMode === 'employees' ? 'selected' : '' ?>>Employee import</option>
            <option value="attendance" <?= $importMode === 'attendance' ? 'selected' : '' ?>>Attendance import</option>
        </select>
            <label>Attendance month
                <input type="month" name="attendance_month" form="import-form" value="<?= htmlspecialchars($attendanceMonth) ?>">
            </label>
    </div>

    <form id="import-form" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">

        <div class="file-drop">
            <i data-lucide="file-up"></i>
            <strong>Upload spreadsheet</strong>
            <p>Accepts .csv, legacy .xls, and .xlsx files. Attendance import supports eSSL biometric report columns.</p>
            <input name="sheet" type="file" accept=".xls,.xlsx,.csv" required>
        </div>

        <div class="form-actions">
            <button class="button"><i data-lucide="upload"></i>Validate & import</button>
        </div>
    </form>

    <?php if ($report): ?>
        <div class="payroll-totals">
            <div><span>Created</span><b><?= (int)($report['created'] ?? 0) ?></b></div>
            <div><span>Updated</span><b><?= (int)($report['updated'] ?? 0) ?></b></div>
            <div><span>Rejected rows</span><b><?= count($report['errors'] ?? []) ?></b></div>
        </div>

        <?php if (($report['errors'] ?? []) !== []): ?>
            <div class="notice">
                <?php foreach ($report['errors'] as $error): ?>
                    <?= htmlspecialchars($error) ?><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php hrPageEnd(); ?>
