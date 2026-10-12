<?php
require '../config/database.php'; require '../includes/functions.php';
if (is_logged_in()) { header('Location: '.dashboard_url()); exit; }
$notice=''; $error=''; $step='request';
// Store only a random reset flow nonce in the browser session, not the OTP itself.
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf(); $action=$_POST['action']??'';
    if ($action==='request') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $key = hash('sha256', $email);
            try {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT IGNORE INTO password_reset_otps(email_hash) VALUES(?)')->execute([$key]);
                $q = $pdo->prepare('SELECT * FROM password_reset_otps WHERE email_hash=? FOR UPDATE');
                $q->execute([$key]);
                $entry = $q->fetch(PDO::FETCH_ASSOC);
                if (!$entry) throw new RuntimeException('Could not initialize password reset request.');

                // Use MySQL's clock for cooldowns so PHP/MySQL timezone differences cannot block valid codes.
                $q = $pdo->prepare("SELECT (window_started_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)) AS window_expired,
                    (last_sent_at IS NULL OR last_sent_at <= DATE_SUB(NOW(), INTERVAL 60 SECOND)) AS cooldown_passed
                    FROM password_reset_otps WHERE id=?");
                $q->execute([(int)$entry['id']]);
                $limits = $q->fetch(PDO::FETCH_ASSOC) ?: [];
                if (!empty($limits['window_expired'])) {
                    $pdo->prepare('UPDATE password_reset_otps SET request_count=0,window_started_at=NOW() WHERE id=?')->execute([(int)$entry['id']]);
                    $entry['request_count'] = 0;
                }
                $allowed = (int)$entry['request_count'] < 5 && !empty($limits['cooldown_passed']);
                $q = $pdo->prepare("SELECT id,name,email FROM users WHERE LOWER(email)=? AND status='active' LIMIT 1");
                $q->execute([$email]);
                $user = $q->fetch(PDO::FETCH_ASSOC);

                if (!$allowed) {
                    $pdo->commit();
                    $error = 'Please wait at least 60 seconds before requesting another code. You can request up to five codes per hour.';
                    $step = 'request';
                } elseif (!$user) {
                    $pdo->commit();
                    // Keep the same public response for unknown accounts to avoid account enumeration.
                    $notice = 'If an active account matches that email, a verification code will be sent shortly. Check your inbox and spam folder.';
                    $step = 'request';
                } else {
                    $otp = (string)random_int(100000, 999999);
                    $safe = htmlspecialchars((string)$user['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $html = '<!doctype html><html lang="en"><body style="font-family:Arial,sans-serif;color:#243044;line-height:1.65"><div style="max-width:600px;margin:28px auto;padding:28px;border:1px solid #e2e7ee;border-radius:12px"><h1 style="color:#26364a">LuxeStay Hotel</h1><h2>Password reset verification</h2><p>Dear '.$safe.',</p><p>Use this six-digit code to reset your password:</p><p style="padding:18px;text-align:center;background:#f7f8fa;border-radius:8px;font-size:32px;font-weight:bold;letter-spacing:8px">'.$otp.'</p><p><strong>This code expires in 10 minutes.</strong> Do not share it with anyone.</p><p>If you did not request this, ignore this email. Your password will not change.</p><p>Kind regards,<br>LuxeStay Hotel Support</p></div></body></html>';
                    $plain = "Dear {$user['name']},\n\nYour LuxeStay password reset code is {$otp}. It expires in 10 minutes. Do not share this code with anyone. If you did not request this, ignore this email.\n\nLuxeStay Hotel Support";

                    // Invalidate the previous code before sending the new one.
                    $pdo->prepare('UPDATE password_reset_otps SET user_id=?,otp_hash=NULL,expires_at=NULL,attempts=0,verified_at=NULL,reset_token_hash=NULL,reset_expires_at=NULL WHERE id=?')
                        ->execute([(int)$user['id'], (int)$entry['id']]);
                    $pdo->commit();

                    $sent = false;
                    try {
                        $sent = hotel_send_email((string)$user['email'], (string)$user['name'],
                            'LuxeStay | Password Reset Verification Code', $html, $plain);
                    } catch (Throwable $mailError) {
                        error_log('LuxeStay password reset mail exception: '.get_class($mailError).': '.$mailError->getMessage());
                    }

                    if ($sent) {
                        $pdo->prepare('UPDATE password_reset_otps SET otp_hash=?,expires_at=DATE_ADD(NOW(),INTERVAL 10 MINUTE),last_sent_at=NOW(),request_count=request_count+1 WHERE id=?')
                            ->execute([password_hash($otp, PASSWORD_DEFAULT), (int)$entry['id']]);
                        $_SESSION['reset_email_hash'] = $key;
                        unset($_SESSION['reset_verified_token']);
                        $notice = 'Your password reset code has been sent. Check your inbox and spam/junk folder. The code expires in 10 minutes.';
                        $step = 'verify';
                    } else {
                        // Do not consume cooldown or leave a valid OTP when email delivery fails.
                        $pdo->prepare('UPDATE password_reset_otps SET otp_hash=NULL,expires_at=NULL,last_sent_at=NULL WHERE id=?')->execute([(int)$entry['id']]);
                        unset($_SESSION['reset_email_hash'], $_SESSION['reset_verified_token']);
                        error_log('LuxeStay password reset email delivery failed for reset record '.(int)$entry['id'].'.');
                        $error = 'We could not send the verification email right now. Please check the mail configuration or try again later.';
                        $step = 'request';
                    }
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('LuxeStay password reset request failed: '.get_class($e).': '.$e->getMessage());
                $error = 'We could not process your password reset request. Please try again later.';
                $step = 'request';
            }
        }
    } elseif ($action==='verify') {
$key=$_SESSION['reset_email_hash']??'';$code=trim((string)($_POST['otp']??''));$step='verify';
if (!$key || !preg_match('/^[0-9]{6}$/D',$code)) $error='Enter the six-digit
code from your email.'; else { $pdo->beginTransaction();
$q=$pdo->prepare('SELECT * FROM password_reset_otps WHERE email_hash=? FOR
UPDATE');$q->execute([$key]);$entry=$q->fetch(); $valid=$entry &&
$entry['user_id'] && $entry['otp_hash'] && $entry['expires_at'] &&
strtotime($entry['expires_at'])>time() && (int)$entry['attempts']<5; if ($valid
&& password_verify($code,$entry['otp_hash'])) {
$token=bin2hex(random_bytes(32)); $_SESSION['reset_verified_token']=$token;
$pdo->prepare('UPDATE password_reset_otps SET
otp_hash=NULL,expires_at=NULL,verified_at=NOW(),reset_token_hash=?,reset_expires_at=DATE_ADD(NOW(),INTERVAL
10 MINUTE) WHERE id=?')->execute([hash('sha256',$token),$entry['id']]);
$step='reset';$notice='Code verified. Set a new password.'; } else { if ($entry)
$pdo->prepare('UPDATE password_reset_otps SET attempts=LEAST(attempts+1,5) WHERE
id=?')->execute([$entry['id']]); $error='Invalid or expired code. You can
request a new code.'; } $pdo->commit(); } } elseif ($action==='reset') {
$step='reset';$key=$_SESSION['reset_email_hash']??'';$token=$_SESSION['reset_verified_token']??'';
$password=(string)($_POST['password']??'');$confirm=(string)($_POST['confirm_password']??'');
if (strlen($password)<12 || strlen($password)>72 || !preg_match('/[A-Z]/',$password) || !preg_match('/[a-z]/',$password) || !preg_match('/[0-9]/',$password) || !preg_match('/[^A-Za-z0-9]/',$password)) $error='Password must be 12–72 characters and include uppercase, lowercase, a number, and a special character.'; elseif ($password!==$confirm) $error='Passwords do not
match.'; elseif (!$key || !$token) $error='Your reset session expired. Request a
new code.'; else { $pdo->beginTransaction(); $q=$pdo->prepare('SELECT * FROM
password_reset_otps WHERE email_hash=? FOR
UPDATE');$q->execute([$key]);$entry=$q->fetch(); if (!$entry ||
!$entry['user_id'] || !$entry['reset_token_hash'] ||
!hash_equals($entry['reset_token_hash'],hash('sha256',$token)) ||
strtotime($entry['reset_expires_at'])<=time()) { $error='Reset session expired.
Request a new code.'; } else { $pdo->prepare('UPDATE users SET password=? WHERE
id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$entry['user_id']]);
$pdo->prepare('DELETE FROM password_reset_otps WHERE
id=?')->execute([$entry['id']]);
$pdo->prepare('DELETE FROM login_2fa_devices WHERE user_id=?')->execute([(int)$entry['user_id']]);
unset($_SESSION['reset_email_hash'],$_SESSION['reset_verified_token']);
$pdo->commit(); session_regenerate_id(true); flash('success','Password changed
successfully. Please sign in.');
 header('Location:'.url('auth/login.php'));
 exit; } if ($pdo->inTransaction())$pdo->rollBack(); } }
} elseif (!empty($_SESSION['reset_verified_token'])) $step='reset'; elseif
(!empty($_SESSION['reset_email_hash'])) $step='verify'; $page_title='Reset
password';include '../includes/header.php'; ?>
<div class="auth-wrap">
  <div class="card auth-card">
    <span class="eyebrow" style="color: var(--brand)">ACCOUNT RECOVERY</span>
    <h1>Forgot password?</h1>
    <p class="muted">
      Receive a six-digit verification code by email to reset your password.
    </p>
    <?php if($notice): ?>
    <div class="flash success" style="width: 100%"><?=e($notice)?></div>
    <?php endif; ?>
    <?php if($error): ?>
    <div class="flash error" style="width: 100%"><?=e($error)?></div>
    <?php endif; ?>
    <?php if($step==='request'): ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=csrf_token()?>" /><input
        type="hidden"
        name="action"
        value="request"
      />
      <div class="form-group">
        <label>Registered email address</label
        ><input
          class="input"
          type="email"
          name="email"
          autocomplete="email"
          required
        />
      </div>
      <button class="btn btn-primary" style="width: 100%">Send OTP</button>
    </form>
    <?php elseif($step==='verify'): ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=csrf_token()?>" /><input
        type="hidden"
        name="action"
        value="verify"
      />
      <div class="form-group">
        <label>Six-digit email code</label
        ><input
          class="input"
          name="otp"
          inputmode="numeric"
          pattern="[0-9]{6}"
          maxlength="6"
          autocomplete="one-time-code"
          required
        />
      </div>
      <button class="btn btn-primary" style="width: 100%">Verify code</button>
    </form>
    <form method="post" style="margin-top: 12px">
      <input type="hidden" name="csrf" value="<?=csrf_token()?>" /><input
        type="hidden"
        name="action"
        value="request"
      />
      <div class="form-group">
        <label>Resend to email (60-second cooldown)</label
        ><input
          class="input"
          type="email"
          name="email"
          autocomplete="email"
          required
        />
      </div>
      <button class="btn btn-outline" style="width: 100%">
        Request new code
      </button>
    </form>
    <?php else: ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=csrf_token()?>" /><input
        type="hidden"
        name="action"
        value="reset"
      />
      <div class="form-group">
        <label>New password</label
        ><input
          class="input"
          type="password"
          name="password"
          minlength="12"
          maxlength="72"
          autocomplete="new-password"
          required
        />
      </div>
      <p class="muted password-requirements">Password requirements: 12–72 characters, at least one uppercase letter (A–Z), one lowercase letter (a–z), one number (0–9), and one special character (such as !@#$%).</p>
      <div class="form-group">
        <label>Confirm new password</label
        ><input
          class="input"
          type="password"
          name="confirm_password"
          minlength="12"
          maxlength="72"
          autocomplete="new-password"
          required
        />
      </div>
      <button class="btn btn-primary" style="width: 100%">
        Change password
      </button>
    </form>
    <?php endif; ?>
    <p style="margin-top: 18px">
      <a href="<?=url('auth/login.php')?>">← Back to login</a>
    </p>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
