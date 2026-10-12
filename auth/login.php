<?php
require '../config/database.php'; require '../includes/functions.php';
if (is_logged_in()) { header('Location: ' . dashboard_url()); exit; }
$err = '';
$step = !empty($_SESSION['login_2fa_user_id']) ? 'verify' : 'login';

function complete_login(PDO $pdo, array $u): void {
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$u['id'], 'name' => $u['name'],
        'email' => $u['email'], 'role' => $u['role'],
    ];
    unset($_SESSION['login_2fa_user_id'], $_SESSION['login_2fa_email']);
    log_security_event($pdo, 'login_success', (int)$u['id'], 'Successful login after password and 2FA');
    $d = dashboard_url();
    unset($_SESSION['after_login']);
    header('Location: ' . $d);
    exit;
}
function send_login_otp(array $u, string $otp): bool {
    $safe = htmlspecialchars((string)$u['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<!doctype html><html lang="en"><body style="font-family:Arial,sans-serif;color:#243044;line-height:1.65"><div style="max-width:600px;margin:28px auto;padding:28px;border:1px solid #e2e7ee;border-radius:12px"><h1 style="color:#26364a">LuxeStay Hotel</h1><h2>Sign-in verification code</h2><p>Hello '.$safe.',</p><p>Use this six-digit code to finish signing in:</p><p style="padding:18px;text-align:center;background:#f7f8fa;border-radius:8px;font-size:32px;font-weight:bold;letter-spacing:8px">'.$otp.'</p><p><strong>This code expires in 10 minutes.</strong> Never share it with anyone.</p><p>If you did not try to sign in, change your password and contact hotel support.</p><p>Kind regards,<br>LuxeStay Hotel Support</p></div></body></html>';
    $plain = "Hello {$u['name']},\n\nYour LuxeStay sign-in verification code is {$otp}. It expires in 10 minutes. Never share it with anyone.\n\nLuxeStay Hotel Support";
    return hotel_send_email((string)$u['email'], (string)$u['name'], 'LuxeStay | Sign-in Verification Code', $html, $plain);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'login');
    if ($action === 'verify_2fa') {
        $uid = (int)($_SESSION['login_2fa_user_id'] ?? 0);
        $code = trim((string)($_POST['otp'] ?? ''));
        $step = 'verify';
        if (!$uid || !preg_match('/^[0-9]{6}$/D', $code)) {
            $err = 'Enter the six-digit verification code sent to your email.';
        } else {
            $pdo->beginTransaction();
            try {
                $q = $pdo->prepare('SELECT c.*, u.id AS uid, u.name, u.email, u.role, u.status FROM login_2fa_challenges c JOIN users u ON u.id=c.user_id WHERE c.user_id=? FOR UPDATE');
                $q->execute([$uid]); $row = $q->fetch();
                // Staff and administrators are exempt from 2FA. If a role changed
                // while a challenge was pending, finish their login without requiring OTP.
                if ($row && $row['status'] === 'active' && in_array($row['role'], ['admin', 'staff'], true)) {
                    $pdo->prepare('DELETE FROM login_2fa_challenges WHERE user_id=?')->execute([$uid]);
                    $pdo->commit();
                    complete_login($pdo, ['id'=>$row['uid'],'name'=>$row['name'],'email'=>$row['email'],'role'=>$row['role']]);
                }
                $valid = $row && $row['status'] === 'active' && $row['otp_hash'] && (int)$row['attempts'] < 5 && strtotime((string)$row['expires_at']) > time();
                if ($valid && password_verify($code, $row['otp_hash'])) {
                    $pdo->prepare('DELETE FROM login_2fa_challenges WHERE user_id=?')->execute([$uid]);
                    $pdo->commit();
                    $token = bin2hex(random_bytes(32));
                    $pdo->prepare('INSERT INTO login_2fa_devices(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(), INTERVAL 10 DAY)) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),expires_at=VALUES(expires_at),created_at=NOW()')
                        ->execute([$uid, hash('sha256', $token)]);
                    $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
                    setcookie('luxestay_2fa', $token, ['expires'=>time()+864000,'path'=>'/hotel_booking_system/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
                    complete_login($pdo, ['id'=>$row['uid'],'name'=>$row['name'],'email'=>$row['email'],'role'=>$row['role']]);
                } else {
                    if ($row) $pdo->prepare('UPDATE login_2fa_challenges SET attempts=LEAST(attempts+1,5) WHERE user_id=?')->execute([$uid]);
                    $pdo->commit();
                    $err = 'The verification code is incorrect or expired. Please sign in again to request a new code.';
                    unset($_SESSION['login_2fa_user_id'], $_SESSION['login_2fa_email']);
                    $step = 'login';
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('LuxeStay login 2FA verification failed: '.get_class($e).': '.$e->getMessage());
                $err = 'We could not verify your code. Please try signing in again.';
                $step = 'login';
            }
        }
    } elseif ($action === 'resend_2fa') {
        $uid = (int)($_SESSION['login_2fa_user_id'] ?? 0);
        $step = 'verify';
        $q = $pdo->prepare("SELECT id,name,email,role,status FROM users WHERE id=? AND status='active' LIMIT 1");
        $q->execute([$uid]); $u = $q->fetch();
        if (!$u) { unset($_SESSION['login_2fa_user_id'], $_SESSION['login_2fa_email']); $step='login'; $err='Your sign-in session expired. Please sign in again.'; }
        else {
            $q = $pdo->prepare('SELECT created_at FROM login_2fa_challenges WHERE user_id=?'); $q->execute([$uid]); $challenge=$q->fetch();
            if ($challenge && strtotime((string)$challenge['created_at']) > time()-60) $err='Please wait 60 seconds before requesting another code.';
            else {
                $otp=(string)random_int(100000,999999);
                if (send_login_otp($u,$otp)) {
                    $pdo->prepare('INSERT INTO login_2fa_challenges(user_id,otp_hash,expires_at,attempts,created_at) VALUES(?,?,DATE_ADD(NOW(), INTERVAL 10 MINUTE),0,NOW()) ON DUPLICATE KEY UPDATE otp_hash=VALUES(otp_hash),expires_at=VALUES(expires_at),attempts=0,created_at=NOW()')
                        ->execute([$uid,password_hash($otp,PASSWORD_DEFAULT)]);
                    $err='A new verification code has been sent to your email.';
                } else $err='We could not send a verification email. Check the mail settings and try again later.';
            }
        }
    } else {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        if (login_rate_limited($pdo, $email)) {
            $st=$pdo->prepare("SELECT id FROM users WHERE email=? AND status='active' LIMIT 1"); $st->execute([$email]); $u=$st->fetch();
            log_security_event($pdo,'login_rate_limited',$u?(int)$u['id']:null,'Login temporarily blocked by rate limit');
            $err='Too many sign-in attempts. Please wait 15 minutes before trying again.';
        } else {
            $st=$pdo->prepare("SELECT * FROM users WHERE email=? AND status='active' LIMIT 1"); $st->execute([$email]); $u=$st->fetch();
            if ($u && password_verify($password, $u['password'])) {
                clear_login_attempts($pdo,$email);
                // Only customer accounts require email 2FA. Staff and admins sign in
                // with their password as before and do not receive OTP emails.
                if (in_array($u['role'], ['admin', 'staff'], true)) {
                    complete_login($pdo, $u);
                }
                $trustedToken=(string)($_COOKIE['luxestay_2fa']??'');
                if ($trustedToken !== '' && preg_match('/^[a-f0-9]{64}$/D',$trustedToken)) {
                    $q=$pdo->prepare('SELECT id FROM login_2fa_devices WHERE user_id=? AND token_hash=? AND expires_at>NOW() LIMIT 1');
                    $q->execute([(int)$u['id'],hash('sha256',$trustedToken)]);
                    if ($q->fetch()) complete_login($pdo,$u);
                }
                $otp=(string)random_int(100000,999999);
                if (send_login_otp($u,$otp)) {
                    $pdo->prepare('INSERT INTO login_2fa_challenges(user_id,otp_hash,expires_at,attempts,created_at) VALUES(?,?,DATE_ADD(NOW(), INTERVAL 10 MINUTE),0,NOW()) ON DUPLICATE KEY UPDATE otp_hash=VALUES(otp_hash),expires_at=VALUES(expires_at),attempts=0,created_at=NOW()')
                        ->execute([(int)$u['id'],password_hash($otp,PASSWORD_DEFAULT)]);
                    session_regenerate_id(true);
                    $_SESSION['login_2fa_user_id']=(int)$u['id'];
                    $_SESSION['login_2fa_email']=(string)$u['email'];
                    $step='verify';
                    $err='A verification code has been sent to your email. It expires in 10 minutes.';
                } else {
                    error_log('LuxeStay sign-in 2FA email failed for user ID '.(int)$u['id']);
                    $err='We could not send your sign-in verification email. Please try again later or contact support.';
                }
            } else {
                record_login_attempt($pdo,$email);
                log_security_event($pdo,'login_failed',$u?(int)$u['id']:null,'Invalid email/password or inactive account');
                $err='Invalid email or password.';
            }
        }
    }
}
$page_title='Login'; include '../includes/header.php'; ?>
<div class="auth-wrap"><div class="card auth-card">
<span class="eyebrow" style="color:var(--brand)">LuxeStay access</span>
<h1><?= $step==='verify' ? 'Verify your sign-in' : 'Welcome back' ?></h1>
<p class="muted"><?= $step==='verify' ? 'Enter the six-digit code sent to your registered email. This browser will be trusted for 10 days.' : 'Sign in to continue to your account workspace.' ?></p>
<?php if($err): ?><div class="flash <?=strpos($err,'sent to your email') !== false?'success':'error'?>" style="width:100%"><?=e($err)?></div><?php endif; ?>
<?php if($step==='verify'): ?>
<form method="post"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="verify_2fa"><div class="form-group"><label>Six-digit verification code</label><input class="input" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></div><button class="btn btn-primary" style="width:100%">Verify and sign in</button></form>
<form method="post" style="margin-top:12px"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="resend_2fa"><button class="btn btn-outline" style="width:100%">Resend verification code</button></form>
<?php else: ?>
<form method="post"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="login"><div class="form-group"><label>Email address</label><input class="input" type="email" name="email" autocomplete="username" required></div><div class="form-group"><label>Password</label><input class="input" type="password" name="password" autocomplete="current-password" required></div><button class="btn btn-primary" style="width:100%">Sign in</button></form>
<p style="text-align:right;margin-top:10px"><a href="<?=url('auth/forgot-password.php')?>">Forgot password?</a></p>
<?php endif; ?></div></div>
<?php include '../includes/footer.php'; ?>
