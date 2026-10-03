<?php
require_once __DIR__.'/core.php';
$companyId = hrCompanyId($hrDb);
$company = hrCompany($hrDb);
$today = date('Y-m-d');
$activeEmployees = (int)($hrDb->query("SELECT COUNT(*) n FROM hr_employees WHERE company_id=$companyId AND status='Active'")->fetch_assoc()['n'] ?? 0);
$presentCount = (int)($hrDb->query("SELECT COUNT(*) n FROM hr_attendance WHERE company_id=$companyId AND attendance_date='$today' AND status IN ('Present','Late','Paid Leave','Work From Home')")->fetch_assoc()['n'] ?? 0);
$pendingLeaves = (int)($hrDb->query("SELECT COUNT(*) n FROM hr_leave_requests WHERE company_id=$companyId AND status='Pending'")->fetch_assoc()['n'] ?? 0);
$attendanceRate = $activeEmployees ? round(($presentCount / $activeEmployees) * 100) : 0;
$nextPayroll = date('F Y');
$insights = hrAiInsights($hrDb, $companyId);
hrPageStart('AI HR assistant');
?>
<div class="page-head">
    <div>
        <h2>AI HR assistant</h2>
        <p>Smart recommendations for workforce health, attendance, and payroll planning.</p>
    </div>
    <div class="button-group">
        <a class="button secondary" href="index.php">Overview</a>
        <a class="button" href="employees.php#add">Add employee</a>
    </div>
</div>

<div class="ai-hero panel">
    <div class="ai-hero-copy">
        <span class="ai-badge">Live assistant</span>
        <h3>HRCloud AI sees the biggest people risks before they become problems.</h3>
        <p>
            Based on your current workforce data, the system recommends actions for attendance follow-ups, leave approvals, and payroll readiness.
        </p>
    </div>
    <div class="sparkline">
        <div class="sparkline-bar" style="height: 46%"></div>
        <div class="sparkline-bar" style="height: 62%"></div>
        <div class="sparkline-bar" style="height: 55%"></div>
        <div class="sparkline-bar" style="height: 76%"></div>
        <div class="sparkline-bar" style="height: 82%"></div>
        <div class="sparkline-bar" style="height: 87%"></div>
    </div>
</div>

<div class="ai-grid">
    <article class="panel ai-card">
        <div class="card-header">
            <span class="chip success">Attendance</span>
            <i data-lucide="activity"></i>
        </div>
        <strong><?= $attendanceRate ?>%</strong>
        <p>Attendance rate today across active employees.</p>
        <small><?= $presentCount ?> present today out of <?= $activeEmployees ?> active employees.</small>
    </article>

    <article class="panel ai-card">
        <div class="card-header">
            <span class="chip warning">Leave</span>
            <i data-lucide="calendar-clock"></i>
        </div>
        <strong><?= $pendingLeaves ?></strong>
        <p>Pending leave requests awaiting action.</p>
        <small>Action recommended: approve or decline before the next shift plan.</small>
    </article>

    <article class="panel ai-card">
        <div class="card-header">
            <span class="chip info">Payroll</span>
            <i data-lucide="wallet"></i>
        </div>
        <strong><?= $nextPayroll ?></strong>
        <p>Monthly payroll is being monitored for readiness.</p>
        <small>Keep statutory deductions and employee salaries aligned with policy.</small>
    </article>
</div>

<div class="panel">
    <h3>AI recommendations</h3>
    <div class="ai-row-list">
        <?php foreach ($insights as $item): ?>
            <div class="ai-row">
                <div>
                    <strong><?= htmlspecialchars($item['title']) ?></strong>
                    <p><?= htmlspecialchars($item['message']) ?></p>
                </div>
                <a href="<?= htmlspecialchars($item['link']) ?>"><?= htmlspecialchars($item['action']) ?></a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="panel">
    <h3>Suggested HR action checklist</h3>
    <ul class="checklist">
        <li>Review attendance anomalies from the last 24 hours.</li>
        <li>Approve leave requests that do not conflict with staffing minimums.</li>
        <li>Finalize payroll before the company cutoff date.</li>
        <li>Schedule a short employee engagement touchpoint with high-risk teams.</li>
    </ul>
</div>
<?php hrPageEnd(); ?>
