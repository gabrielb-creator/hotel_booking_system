<?php
require '../../config/database.php';
require '../../includes/functions.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('admin/rooms/index.php'));
    exit;
}
verify_csrf();
$id = (int)($_POST['id'] ?? 0);
try {
    $stmt = $pdo->prepare('DELETE FROM rooms WHERE id=?');
    $stmt->execute([$id]);
    if ($stmt->rowCount()) {
        log_security_event($pdo, 'room_deleted', (int)$_SESSION['user']['id'], 'Room inventory item deleted');
        flash('success', 'Room deleted.');
    } else {
        flash('error', 'Room not found.');
    }
} catch (Throwable $e) {
    error_log('LuxeStay room deletion failed: ' . get_class($e));
    flash('error', 'Room has booking history. Set it to maintenance instead.');
}
header('Location: ' . url('admin/rooms/index.php'));
exit;
?>
