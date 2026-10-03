<?php
require_once __DIR__.'/core.php';
hrSetNoCacheHeaders();
$company = hrCompany($hrDb);
$companies = hrCompanies($hrDb);
$selectedCompanyId = (int)($_GET['company_id'] ?? $_SESSION['hr_company_id'] ?? ($company['id'] ?? 0));
if ($selectedCompanyId > 0) {
    $_SESSION['hr_company_id'] = $selectedCompanyId;
}
$selectedCompany = $company;
foreach ($companies as $entry) {
    if ((int)$entry['id'] === $selectedCompanyId) {
        $selectedCompany = $entry;
        break;
    }
}

if (isset($_SESSION['hr_employee_id']) || isset($_SESSION['hr_employee_company_id']) || isset($_SESSION['hr_employee_role'])) {
    $employeeId = (int)($_SESSION['hr_employee_id'] ?? 0);
    if ($employeeId > 0) {
        $activeEmployee = hrEmployeeById($hrDb, $employeeId);
        if ($activeEmployee) {
            header('Location: employee_dashboard.php');
            exit;
        }
    }

    hrClearEmployeeSession();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedCompanyId = (int)($_POST['company_id'] ?? 0);
    if ($selectedCompanyId > 0) {
        $_SESSION['hr_company_id'] = $selectedCompanyId;
    }

    $code = trim((string)($_POST['employee_code'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role = strtoupper(trim((string)($_POST['role'] ?? 'Employee')));
    if ($code === '' || $password === '') {
        hrFlash('Employee code and password are required.', 'error');
        header('Location: employee_login.php');
        exit;
    }

    $employee = hrAuthenticateEmployee($hrDb, $code, $password, $role, $selectedCompanyId);
    if (!$employee) {
        hrFlash('Invalid credentials for the selected role.', 'error');
        header('Location: employee_login.php');
        exit;
    }

    header('Location: employee_dashboard.php');
    exit;
}

if (isset($_SESSION['hr_company_id'])) {
    foreach ($companies as $entry) {
        if ((int)$entry['id'] === (int)$_SESSION['hr_company_id']) {
            $selectedCompany = $entry;
            break;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee login | HRCloud AI HRMS</title>
    <link rel="stylesheet" href="saas.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="auth-body" style="--brand:<?= htmlspecialchars($company['primary_color'] ?? '#2563eb') ?>; --brand-dark: <?= htmlspecialchars($company['primary_color'] ?? '#1d4ed8') ?>;">
<div class="auth-shell">
    <aside class="auth-visual">
        <div class="auth-brand-row">
            <div class="auth-logo"><?php if (!empty($selectedCompany['logo_url'])): ?><img src="<?= htmlspecialchars($selectedCompany['logo_url']) ?>" alt="Company logo" style="width:100%;height:100%;object-fit:<?= htmlspecialchars((string)($selectedCompany['logo_fit'] ?? 'contain')) ?>;border-radius:14px;display:block;">
            <?php else: ?>HR<?php endif; ?></div>
            <div>
                <span class="eyebrow">WORKFORCE OS</span>
                <strong>HRCloud AI</strong>
            </div>
        </div>

        <div class="auth-company-box">
            <div class="company-mark"><?php if (!empty($selectedCompany['logo_url'])): ?><img src="<?= htmlspecialchars($selectedCompany['logo_url']) ?>" alt="Company logo" style="width:100%;height:100%;object-fit:<?= htmlspecialchars((string)($selectedCompany['logo_fit'] ?? 'contain')) ?>;border-radius:14px;display:block;">
            <?php else: ?><?= htmlspecialchars(substr($selectedCompany['company_name'] ?? 'CO', 0, 2) ?: 'CO') ?><?php endif; ?></div>
            <div>
                <small>Company workspace</small>
                <h1><?= htmlspecialchars($selectedCompany['company_name'] ?? 'Choose a company') ?></h1>
            </div>
        </div>

        <div class="company-list">
            <?php foreach ($companies as $entry): ?>
                <a href="?company_id=<?= (int)$entry['id'] ?>" class="company-item <?= ((int)$entry['id'] === (int)($selectedCompany['id'] ?? 0)) ? 'active' : '' ?>">
                    <div class="company-badge"><?php if (!empty($entry['logo_url'])): ?><img src="<?= htmlspecialchars($entry['logo_url']) ?>" alt="Logo" style="width:100%;height:100%;object-fit:<?= htmlspecialchars((string)($entry['logo_fit'] ?? 'contain')) ?>;border-radius:10px;display:block;">
                    <?php else: ?><?= htmlspecialchars(substr($entry['company_name'] ?? 'C', 0, 2) ?: 'CO') ?><?php endif; ?></div>
                    <div class="company-item-text">
                        <strong><?= htmlspecialchars($entry['company_name'] ?? 'Company') ?></strong>
                        <small><?= htmlspecialchars($entry['company_code'] ?? 'CODE') ?></small>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <p class="auth-copy">Track attendance, approvals, leave, and operational visibility with one executive-ready HR platform.</p>
    </aside>

    <main class="auth-panel">
        <div class="auth-card">
            <div class="auth-header">
                <div class="brand-pill">HR</div>
                <div>
                    <small>Secure access</small>
                    <h2>Employee login</h2>
                </div>
            </div>

            <p class="auth-subtitle">Use your employee code and password to view your attendance and company updates.</p>

            <form method="post" class="auth-form">
                <input type="hidden" name="csrf" value="<?= hrCsrf() ?>">
                <input type="hidden" name="company_id" value="<?= (int)($selectedCompany['id'] ?? 0) ?>">
                <label>Login as
                    <select name="role">
                        <option value="Employee">Employee</option>
                        <option value="HR">HR</option>
                        <option value="IT">IT</option>
                        <option value="MD">MD</option>
                    </select>
                </label>
                <label>Employee code
                    <input name="employee_code" placeholder="EMP-001" required>
                </label>
                <label>Password
                    <input type="password" name="password" placeholder="Enter password" required>
                </label>
                <button class="button" type="submit">Login</button>
            </form>

            <div class="auth-footer">
                <span>Default employee login</span>
                <strong>EMP-001 / 123456</strong>
            </div>
        </div>
    </main>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
