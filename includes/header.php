<?php require_once __DIR__ . '/functions.php'; ?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?= e($page_title ?? 'LuxeStay Hotel') ?></title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>" />
  </head>
  <body>
    <header class="site-header">
      <div class="container nav">
        <a class="brand" href="<?= url('index.php') ?>"
          ><span>◆</span> LuxeStay</a
        >
        <nav>
          <?php if (is_admin()): ?><a href="<?= url('admin/index.php') ?>"
            >Overview</a
          ><a href="<?= url('booking/allBookings.php') ?>">All bookings</a
          ><a href="<?= url('admin/rooms/index.php') ?>">Rooms</a
          ><a href="<?= url('admin/users/index.php') ?>">Users</a
          ><a href="<?= url('admin/security-log.php') ?>">Security log</a
          ><?php elseif (is_staff()): ?><a href="<?= url('staff/index.php') ?>"
            >Overview</a
          ><a href="<?= url('booking/allBookings.php') ?>">All bookings</a
          ><a href="<?= url('staff/index.php#available-rooms') ?>"
            >Available rooms</a
          ><?php else: ?><a href="<?= url('index.php') ?>">Home</a
          ><a href="<?= url('rooms/index.php') ?>">Rooms</a
          ><?php if (is_customer()): ?><a
            href="<?= url('account/dashboard.php') ?>"
            >My Bookings</a
          ><?php endif; ?><?php endif; ?>
        </nav>
        <div class="nav-actions">
          <?php if (is_logged_in()): ?><span class="hello"
            >Hi, <?= e($_SESSION['user']['name']) ?> · <?=
            e(ucfirst(user_role())) ?></span
          ><form method="post" action="<?= url('auth/logout.php') ?>" style="display:inline">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <button class="btn btn-outline" type="submit">Logout</button>
          </form
          ><?php else: ?><a href="<?= url('auth/login.php') ?>">Login</a
          ><a class="btn btn-primary" href="<?= url('auth/register.php') ?>"
            >Create account</a
          ><?php endif; ?>
        </div>
      </div>
    </header>
    <?php if ($m = flash('success')): ?>
    <div class="flash success"><?= e($m) ?></div>
    <?php endif; ?><?php if ($m = flash('error')): ?>
    <div class="flash error"><?= e($m) ?></div>
    <?php endif; ?>
    <main></main>
  </body>
</html>
