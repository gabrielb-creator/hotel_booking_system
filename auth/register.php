<?php
require '../config/database.php'; require '../includes/functions.php';
if (is_logged_in()) { header('Location: ' . dashboard_url()); exit; }
$err='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf();
 $name=trim($_POST['name']??''); $email=strtolower(trim($_POST['email']??'')); $password=$_POST['password']??'';
 if(strlen($name)<2 || !filter_var($email,FILTER_VALIDATE_EMAIL))
  $err='Enter a valid name and email address.';
 elseif(strlen($password)<12 || strlen($password)>72 || !preg_match('/[A-Z]/',$password) || !preg_match('/[a-z]/',$password) || !preg_match('/[0-9]/',$password) || !preg_match('/[^A-Za-z0-9]/',$password))
  $err='Password must be 12–72 characters and include uppercase, lowercase, a number, and a special character.';
 else { $st=$pdo->prepare('SELECT id FROM users WHERE email=?');
  $st->execute([$email]); if($st->fetch()) $err='Email is already registered.';
else { $pdo->prepare("INSERT INTO users(name,email,password,role,status)
VALUES(?,?,?,'customer','active')")->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
flash('success','Customer account created. You can now sign in.');
header('Location: '.url('auth/login.php')); exit; } } } $page_title='Create
account'; include '../includes/header.php'; ?>
<div class="auth-wrap">
  <div class="card auth-card">
    <h1>Create your account</h1>
    <p class="muted">
      Customer accounts can browse rooms and manage their own bookings.
    </p>
    <?php if($err): ?>
    <div class="flash error" style="width: 100%"><?=e($err)?></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=csrf_token()?>" />
      <div class="form-group">
        <label>Full name</label
        ><input class="input" name="name" autocomplete="name" required />
      </div>
      <div class="form-group">
        <label>Email address</label
        ><input
          class="input"
          type="email"
          name="email"
          autocomplete="email"
          required
        />
      </div>
      <div class="form-group">
        <label>Password</label
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
      <button class="btn btn-primary" style="width: 100%">
        Create customer account
      </button>
    </form>
  </div>
</div>
<?php include '../includes/footer.php'; ?>2
