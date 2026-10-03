<?php
require_once __DIR__ . '/core.php';
$currentEmployee = hrCurrentEmployee($hrDb);
$companyId = hrCompanyId($hrDb);
$id = (int)($_GET['id'] ?? 0);
$st = $hrDb->prepare('SELECT p.*, e.*, r.payroll_month FROM hr_payslips p JOIN hr_employees e ON e.id = p.employee_id JOIN hr_payroll_runs r ON r.id = p.payroll_run_id WHERE p.id = ? AND e.company_id = ?');
$st->bind_param('ii', $id, $companyId);
$st->execute();
$slip = $st->get_result()->fetch_assoc();
$st->close();
if (!$slip || (strtoupper((string)($currentEmployee['role'] ?? '')) === 'EMPLOYEE' && (int)$slip['employee_id'] !== (int)$currentEmployee['id'])) exit('Payslip not found.');

$company = hrCompany($hrDb);
$settings = $hrDb->query("SELECT * FROM hr_settings WHERE company_id=$companyId")->fetch_assoc() ?: [];
$leaveRows = $hrDb->query("SELECT leave_type, start_date, end_date, status FROM hr_leave_requests WHERE company_id=$companyId AND employee_id=" . (int)$slip['employee_id'] . " AND status='Approved' AND start_date <= '" . $hrDb->real_escape_string(($slip['payroll_month'] ?? date('Y-m')) . '-31') . "' AND end_date >= '" . $hrDb->real_escape_string(($slip['payroll_month'] ?? date('Y-m')) . '-01') . "' ORDER BY start_date")->fetch_all(MYSQLI_ASSOC);
$leaveDetails = [];
foreach ($leaveRows as $leave) $leaveDetails[] = $leave['leave_type'] . ': ' . $leave['start_date'] . ' to ' . $leave['end_date'];
function hrPayslipAmountWords(float $amount): string {
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $underThousand = static function (int $number) use ($ones, $tens): string {
        $words = '';
        if ($number >= 100) { $words .= $ones[intdiv($number, 100)] . ' Hundred '; $number %= 100; }
        if ($number >= 20) $words .= $tens[intdiv($number, 10)] . ' ' . $ones[$number % 10];
        elseif ($number > 0) $words .= $ones[$number];
        return trim($words);
    };
    $number = (int)round($amount);
    if ($number === 0) return 'Zero Only';
    $parts = [];
    foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand'], [1, '']] as [$divisor, $label]) {
        if ($number >= $divisor) { $parts[] = $underThousand(intdiv($number, $divisor)) . ($label ? ' ' . $label : ''); $number %= $divisor; }
    }
    return trim(implode(' ', $parts)) . ' Only';
}
$template = (string)($settings['payslip_template'] ?? '');
if ($template === '') $template = '<div class="slip-head"><div><h1>{{company_name}}</h1><p>{{company_address}}</p></div><div><b>PAYSLIP</b><p>{{payroll_month}}</p></div></div><div class="slip-grid"><div><b>{{employee_name}}</b><p>{{employee_code}} · {{designation}}</p></div><div><b>Paid days: {{paid_days}}</b><p>Bank: {{bank_name}}</p></div></div><table><tr><td>Gross earnings</td><td>{{gross}}</td></tr><tr><td>Deductions</td><td>{{deductions}}</td></tr></table><div class="net"><span>Net pay</span><span>{{net_pay}}</span></div><p>{{payslip_footer}}</p>';
$values = [
    'company_logo' => !empty($company['logo_url']) ? '<img src="' . htmlspecialchars($company['logo_url'], ENT_QUOTES, 'UTF-8') . '" alt="Company logo" style="max-width:180px;max-height:60px;object-fit:contain">' : '',
    'company_name' => $company['company_name'] ?? '', 'company_address' => $company['address'] ?? '', 'company_email' => $company['email'] ?? '', 'company_phone' => $company['phone'] ?? '',
    'employee_name' => trim(($slip['first_name'] ?? '') . ' ' . ($slip['last_name'] ?? '')), 'employee_code' => $slip['employee_code'] ?? '',
    'device_employee_id' => $slip['device_employee_id'] ?? '', 'designation' => $slip['designation'] ?? '', 'department' => $slip['department'] ?? '', 'join_date' => $slip['join_date'] ?? '',
    'uan' => $slip['uan'] ?? '', 'esi_no' => $slip['esi_no'] ?? '', 'aadhar' => $slip['aadhar'] ?? '', 'location' => $slip['city'] ?? ($slip['state'] ?? ''), 'mode_of_pay' => 'Bank Transfer', 'days_pay' => date('t', strtotime(($slip['payroll_month'] ?? date('Y-m')) . '-01')),
    'bank_name' => $slip['bank_name'] ?? '', 'bank_account' => $slip['bank_account'] ?? '', 'pan' => $slip['pan'] ?? '',
    'payroll_month' => date('F Y', strtotime(($slip['payroll_month'] ?? date('Y-m')) . '-01')), 'paid_days' => $slip['paid_days'] ?? 0,
    'basic' => hrMoney((float)($slip['basic'] ?? 0)), 'hra' => hrMoney((float)($slip['hra'] ?? 0)), 'allowance' => hrMoney((float)($slip['allowance'] ?? 0)), 'gross' => hrMoney((float)($slip['gross'] ?? 0)),
    'pf' => hrMoney((float)($slip['pf'] ?? 0)), 'esi' => hrMoney((float)($slip['esi'] ?? 0)), 'professional_tax' => hrMoney((float)($slip['professional_tax'] ?? 0)), 'employer_pf' => hrMoney((float)($slip['pf'] ?? 0)), 'employer_esi' => hrMoney((float)($slip['esi'] ?? 0)),
    'deductions' => hrMoney((float)($slip['total_deductions'] ?? 0)), 'net_pay' => hrMoney((float)($slip['net_pay'] ?? 0)),
    'net_pay_words' => hrPayslipAmountWords((float)($slip['net_pay'] ?? 0)), 'leave_details' => implode(' | ', $leaveDetails) ?: 'No approved leave recorded',
    'payslip_footer' => $settings['payslip_footer'] ?? ''
];
foreach ($values as $key => $value) $template = str_replace('{{' . $key . '}}', $key === 'company_logo' ? (string)$value : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'), $template);
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payslip <?= htmlspecialchars($slip['payroll_month']) ?></title><link rel="stylesheet" href="saas.css"><style>body{background:#eef4fb}.slip-shell{max-width:820px;margin:28px auto}.slip-actions{margin-bottom:14px}@media print{.no-print{display:none!important}body{background:#fff}.slip-shell{margin:0}}</style></head><body><div class="slip-shell"><div class="no-print slip-actions"><button class="button" onclick="window.print()">Print / Save PDF</button><a class="button secondary" href="formats.php">Edit design</a></div><div class="slip"><?= $template ?></div></div></body></html>