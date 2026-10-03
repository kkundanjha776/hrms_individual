<?php
require_once __DIR__.'/core.php';
hrSetNoCacheHeaders();

hrClearEmployeeSession();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_unset();
session_destroy();

header('Location: employee_login.php');
exit;
