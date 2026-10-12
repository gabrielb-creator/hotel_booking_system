<?php
// Harden session handling before a session is started.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/hotel_booking_system/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Browser security headers. CSP permits the local app and its Unsplash room images.
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' https://images.unsplash.com data:; style-src 'self' 'unsafe-inline'; script-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    header('Cache-Control: no-store, max-age=0');
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url($p = '') { return '/hotel_booking_system/' . ltrim($p, '/'); }
function is_logged_in() { return !empty($_SESSION['user']); }
function user_role() { return $_SESSION['user']['role'] ?? null; }
function is_admin() { return is_logged_in() && user_role() === 'admin'; }
function is_staff() { return is_logged_in() && user_role() === 'staff'; }
function is_customer() { return is_logged_in() && user_role() === 'customer'; }

/**
 * Re-check account status and role on every request so deactivation/role changes
 * take effect immediately instead of waiting for a fresh login.
 */
if (!empty($_SESSION['user']['id']) && isset($pdo) && $pdo instanceof PDO) {
    try {
        $authCheck = $pdo->prepare('SELECT id,name,email,role,status FROM users WHERE id=? LIMIT 1');
        $authCheck->execute([(int)$_SESSION['user']['id']]);
        $freshUser = $authCheck->fetch();
        if (!$freshUser || $freshUser['status'] !== 'active') {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $cookie = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'] ?? '', (bool)$cookie['secure'], (bool)$cookie['httponly']);
            }
            session_regenerate_id(true);
        } else {
            $_SESSION['user'] = [
                'id' => (int)$freshUser['id'],
                'name' => $freshUser['name'],
                'email' => $freshUser['email'],
                'role' => $freshUser['role'],
            ];
        }
    } catch (Throwable $e) {
        error_log('LuxeStay session revalidation failed: ' . get_class($e));
    }
}

/** Hash network identifiers before storing them in security logs. */
function security_ip_hash(): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash('sha256', $ip);
}

function log_security_event(PDO $pdo, string $eventType, ?int $userId = null, string $details = ''): void {
    try {
        $stmt = $pdo->prepare('INSERT INTO security_events(event_type,user_id,ip_hash,details) VALUES(?,?,?,?)');
        $stmt->execute([substr($eventType, 0, 60), $userId, security_ip_hash(), substr($details, 0, 255)]);
    } catch (Throwable $e) {
        error_log('LuxeStay security event could not be recorded: ' . get_class($e));
    }
}

/** Limit password guessing by normalized email and source IP. */
function login_rate_limited(PDO $pdo, string $email): bool {
    $emailHash = hash('sha256', strtolower(trim($email)));
    $ipHash = security_ip_hash();
    $stmt = $pdo->prepare("SELECT
        SUM(email_hash=? AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)) AS email_failures,
        SUM(ip_hash=? AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)) AS ip_failures
        FROM login_attempts");
    $stmt->execute([$emailHash, $ipHash]);
    $counts = $stmt->fetch() ?: [];
    return (int)($counts['email_failures'] ?? 0) == 5 || (int)($counts['ip_failures'] ?? 0) >= 20;
}

function record_login_attempt(PDO $pdo, string $email): void {
    $stmt = $pdo->prepare('INSERT INTO login_attempts(email_hash,ip_hash,attempted_at) VALUES(?,?,NOW())');
    $stmt->execute([hash('sha256', strtolower(trim($email))), security_ip_hash()]);
}

function clear_login_attempts(PDO $pdo, string $email): void {
    $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE email_hash=? OR ip_hash=?');
    $stmt->execute([hash('sha256', strtolower(trim($email))), security_ip_hash()]);
}
function dashboard_url() { if (is_admin()) return url('admin/index.php'); if (is_staff()) return url('staff/index.php'); return url('account/dashboard.php'); }
function require_login() { if (!is_logged_in()) { header('Location: ' . url('auth/login.php')); exit; } }
function require_admin() { require_login(); if (!is_admin()) { http_response_code(403); exit('Forbidden: administrator access required.'); } }
function require_staff() { require_login(); if (!is_staff()) { http_response_code(403); exit('Forbidden: receptionist/staff access required.'); } }
function require_customer() { require_login(); if (!is_customer()) { http_response_code(403); exit('This page is for customer accounts only.'); } }
function require_staff_or_admin() { require_login(); if (!is_staff() && !is_admin()) { http_response_code(403); exit('Forbidden.'); } }
function csrf_token() { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf() {
    $expected = (string)($_SESSION['csrf'] ?? '');
    $provided = isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : '';
    if ($expected === '' || !hash_equals($expected, $provided)) {
        http_response_code(419);
        exit('Invalid or expired request token. Refresh the page and try again.');
    }
}
function flash($k, $m = null) { if ($m !== null) { $_SESSION['flash'][$k] = $m; return; } $v = $_SESSION['flash'][$k] ?? null; unset($_SESSION['flash'][$k]); return $v; }
function booking_total($rate, $in, $out) { $a = new DateTime($in); $b = new DateTime($out); $n = max(1, $a->diff($b)->days); return [$n, $n * (float)$rate]; }
function room_available(PDO $pdo, $roomId, $in, $out, $excludeBookingId = 0) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE room_id=? AND id<>? AND status IN ('pending','confirmed') AND NOT(check_out<=? OR check_in>=?)");
    $st->execute([$roomId, (int)$excludeBookingId, $in, $out]);
    return (int)$st->fetchColumn() === 0;
}
/** Shared Gmail SMTP transport for booking updates and password reset OTPs. */
function hotel_send_email(string $recipient, string $name, string $subject, string $html, string $plain): bool {
    require_once __DIR__ . '/../config/mail.php';
    require_once __DIR__ . '/../vendor/autoload.php';
    $username = defined('SMTP_USERNAME') ? trim((string)SMTP_USERNAME) : '';
    $password = defined('SMTP_PASSWORD') ? trim((string)SMTP_PASSWORD) : '';
    $candidate = defined('LUXESTAY_MAIL_FROM') ? trim((string)LUXESTAY_MAIL_FROM) : '';
    $from = filter_var($candidate, FILTER_VALIDATE_EMAIL) ? $candidate : $username;
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL) || !$username || !$password) {
        error_log('LuxeStay email: mail configuration or recipient invalid.'); return false;
    }
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->Port = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
        $mail->SMTPSecure = $mail->Port === 465 ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Timeout = 15;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($from, defined('LUXESTAY_MAIL_FROM_NAME') ? LUXESTAY_MAIL_FROM_NAME : 'LuxeStay Hotel');
        $mail->addAddress($recipient, $name);
        $mail->isHTML(true); $mail->Subject = $subject; $mail->Body = $html; $mail->AltBody = $plain;
        return $mail->send();
    } catch (\Throwable $e) {
        error_log('LuxeStay email: SMTP failed (' . get_class($e) . '): ' . $e->getMessage()); return false;
    }
}

/** Customer-readable status names. Cancelled by reception means declined. */
function booking_status_label(string $status, string $actor = 'admin'): string {
    if ($status === 'confirmed') return 'Approved';
    if ($status === 'cancelled') return $actor === 'staff' ? 'Declined' : 'Cancelled';
    return ucfirst($status);
}
function send_booking_status_email(array $booking, string $status, string $actor = 'admin'): bool {
    $esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $label = booking_status_label($status, $actor);
    $guest = (string)($booking['guest_name'] ?? 'Guest');
    $reference = (string)($booking['reference_no'] ?? '');
    $room = (string)($booking['room_name'] ?? 'Reserved room');
    $in = date('F j, Y', strtotime((string)$booking['check_in']));
    $out = date('F j, Y', strtotime((string)$booking['check_out']));
    $amount = number_format((float)($booking['total_amount'] ?? 0), 2);
    $details = "Reference: {$reference}\nRoom: {$room}\nCheck-in: {$in}\nCheck-out: {$out}\nTotal: PHP {$amount}";
    $plain = "Hello {$guest},\n\nYour booking status is now {$label}.\n\n{$details}\n\nLog in to LuxeStay to review your reservation.\n\nLuxeStay Hotel";
    $html = '<!doctype html><html><body style="font-family:Arial,sans-serif;color:#263244;line-height:1.6"><div style="max-width:600px;margin:24px auto;padding:28px;border:1px solid #e5eaf1;border-radius:14px"><h1>LuxeStay Hotel</h1><h2>Booking status: ' . $esc($label) . '</h2><p>Hello ' . $esc($guest) . ', your reservation status has changed.</p><p><strong>Reference:</strong> ' . $esc($reference) . '<br><strong>Room:</strong> ' . $esc($room) . '<br><strong>Check-in:</strong> ' . $esc($in) . '<br><strong>Check-out:</strong> ' . $esc($out) . '<br><strong>Total:</strong> PHP ' . $esc($amount) . '</p><p>Log in to your account for current booking details.</p><p>LuxeStay Hotel</p></div></body></html>';
    return hotel_send_email((string)($booking['email'] ?? ''), $guest, 'LuxeStay booking ' . $label . ' - ' . $reference, $html, $plain);
}

/** Safely change booking status. Locking the room serializes concurrent approvals for that room. */
function change_booking_status(PDO $pdo, int $bookingId, string $newStatus, ?string $expectedStatus = null, string $actor = 'admin'): array {
    if (!in_array($newStatus, ['pending','confirmed','cancelled','completed'], true)) return [false, 'Invalid booking status.'];
    try {
        $pdo->beginTransaction();
        $q = $pdo->prepare('SELECT b.id,b.room_id,b.check_in,b.check_out,b.status,b.reference_no,b.nights,b.guests,b.total_amount,u.name AS guest_name,u.email,r.name AS room_name FROM bookings b JOIN users u ON u.id=b.user_id JOIN rooms r ON r.id=b.room_id WHERE b.id=? FOR UPDATE');
        $q->execute([$bookingId]); $booking = $q->fetch();
        if (!$booking) { $pdo->rollBack(); return [false, 'Booking was not found.']; }
        if ($expectedStatus !== null && $booking['status'] !== $expectedStatus) { $pdo->rollBack(); return [false, 'This booking has already been updated. Refresh and review the latest status.']; }
        if ($newStatus === $booking['status']) { $pdo->commit(); return [true, 'Booking status is unchanged; no email was sent.']; }
        $notify = true;
        if ($newStatus === 'confirmed') {
            $roomLock = $pdo->prepare('SELECT id,status FROM rooms WHERE id=? FOR UPDATE');
            $roomLock->execute([$booking['room_id']]); $room = $roomLock->fetch();
            if (!$room || $room['status'] !== 'available') { $pdo->rollBack(); return [false, 'This room is not available for booking.']; }
            $overlap = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE room_id=? AND id<>? AND status IN ('pending','confirmed') AND NOT(check_out<=? OR check_in>=?)");
            $overlap->execute([$booking['room_id'], $bookingId, $booking['check_in'], $booking['check_out']]);
            if ((int)$overlap->fetchColumn() > 0) { $pdo->rollBack(); return [false, 'Cannot approve: another pending or confirmed reservation overlaps these dates. The room availability has not changed.']; }
        }
        $up = $pdo->prepare('UPDATE bookings SET status=? WHERE id=?');
        $up->execute([$newStatus, $bookingId]);
        $pdo->commit();

        $actorId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
        log_security_event($pdo, 'booking_status_changed', $actorId,
            'Booking ID ' . $bookingId . ': ' . $booking['status'] . ' -> ' . $newStatus . ' (' . $actor . ')');

        $label = booking_status_label($newStatus, $actor);
        $message = 'Booking status updated to ' . $label . '.';
        if ($notify) {
            $message .= send_booking_status_email($booking, $newStatus, $actor)
                ? ' Customer notification was handed to Gmail SMTP.'
                : ' Email could not be sent; check mail configuration and PHP logs.';
        }
        return [true, $message];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, 'The booking could not be updated. Please try again.'];
    }
}
?>
