<?php
require '../config/database.php';
require '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . (is_logged_in() ? dashboard_url() : url('index.php')));
    exit;
}
verify_csrf();
$userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
if ($userId) log_security_event($pdo, 'logout', $userId, 'User signed out');
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
}
session_destroy();
header('Location: ' . url('index.php'));
exit;
?>
