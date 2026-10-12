<?php
require '../config/database.php'; require '../includes/functions.php'; require_customer();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $id = (int)($_POST['id'] ?? 0);
    $q = $pdo->prepare("SELECT status FROM bookings WHERE id=? AND user_id=? AND status IN ('pending','confirmed')");
    $q->execute([$id, $_SESSION['user']['id']]); $row = $q->fetch();
    if (!$row) flash('error', 'This booking cannot be cancelled.');
    else {
        [$ok, $message] = change_booking_status($pdo, $id, 'cancelled', $row['status'], 'customer');
        flash($ok && strpos($message, 'could not be sent') === false ? 'success' : 'error', $message);
    }
}
header('Location: '.url('account/dashboard.php')); exit;
