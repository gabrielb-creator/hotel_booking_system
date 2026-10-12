<?php
$host = getenv('LUXESTAY_DB_HOST') ?: 'localhost';
$db = getenv('LUXESTAY_DB_NAME') ?: 'hotel_booking';
$user = getenv('LUXESTAY_DB_USER') ?: 'root';
$pass = getenv('LUXESTAY_DB_PASSWORD');
if ($pass === false) {
    // XAMPP local default only. Set LUXESTAY_DB_PASSWORD for non-local environments.
    $pass = '';
}
$charset = 'utf8mb4';
$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_STRINGIFY_FETCHES => false,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log('LuxeStay database connection failed: ' . $e->getCode());
    http_response_code(500);
    exit('The system is temporarily unavailable. Check the server error log and database configuration.');
}
?>
