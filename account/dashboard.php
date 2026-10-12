<?php 
require '../config/database.php';
require '../includes/functions.php';
require_customer();$st=$pdo->prepare("SELECT
b.*,r.name room_name FROM bookings b JOIN rooms r ON r.id=b.room_id WHERE
b.user_id=? ORDER BY b.created_at
DESC");$st->execute([$_SESSION['user']['id']]);$bookings=$st->fetchAll();$today=new
DateTimeImmutable(date('Y-m-d'));$activeStays=array_values(array_filter($bookings,function($b)use($today){return
$b['status']==='confirmed' && $b['check_in'] <= $today->format('Y-m-d') &&
$b['check_out'] > $today->format('Y-m-d');}));$page_title='My Bookings';include
'../includes/header.php';?>
<div class="container page-hero">
  <h1>My bookings</h1>
  <p class="muted">
    Welcome, <?=e($_SESSION['user']['name'])?>. Check your reservations and
    current stay details here.
  </p>
</div>
<section style="padding-top: 10px">
  <div class="container">
    <?php if($activeStays): ?>
    <section class="panel" style="padding: 22px; margin-bottom: 24px">
      <div class="panel-heading">
        <div>
          <h2>Your current stay</h2>
          <p class="muted">Details for your confirmed room reservation.</p>
        </div>
      </div>
      <div class="grid cards-3">
        <?php foreach($activeStays as $stay): $daysLeft=(new DateTimeImmutable(date('Y-m-d')))->diff(new
        DateTimeImmutable($stay['check_out']))->days; ?>
        <article class="card" style="padding: 20px">
          <span class="badge confirmed">Currently staying</span>
          <h3 style="margin-top: 12px"><?=e($stay['room_name'])?></h3>
          <p class="muted">Reference: <?=e($stay['reference_no'])?></p>
          <div class="summary-line">
            <span>Check-in</span
            ><strong
              ><?=e(date('M j, Y',strtotime($stay['check_in'])))?></strong
            >
          </div>
          <div class="summary-line">
            <span>Check-out</span
            ><strong
              ><?=e(date('M j, Y',strtotime($stay['check_out'])))?></strong
            >
          </div>
          <div class="summary-line">
            <span>Days left</span
            ><strong
              ><?= (int)$daysLeft ?>
              day<?= $daysLeft===1?'':'s' ?></strong
            >
          </div>
          <a
            class="btn btn-outline"
            href="<?=url('booking/view.php?id='.$stay['id'])?>"
            >View reservation</a
          >
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?><?php if(!$bookings):?>
    <div class="card empty">
      You have no bookings yet.<br /><br /><a
        class="btn btn-primary"
        href="<?=url('rooms/index.php')?>"
        >Browse rooms</a
      >
    </div>
    <?php else:?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Room</th>
            <th>Dates</th>
            <th>Booked on</th>
            <th>Days left</th>
            <th>Total</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($bookings as $b):?>
          <tr>
            <td>
              <strong><?=e($b['reference_no'])?></strong>
            </td>
            <td><?=e($b['room_name'])?></td>
            <td>
              <?=e($b['check_in'])?>
              → <?=e($b['check_out'])?>
            </td>
            <td>
              <?=e(date('M j, Y',strtotime($b['created_at'])))?><small
                class="table-sub"
                ><?=e(date('g:i A',strtotime($b['created_at'])))?></small
              >
            </td>
            <td>
              <?php if($b['status']==='confirmed' && $b['check_in'] <= date('Y-m-d') && $b['check_out'] >
              date('Y-m-d')): ?><?= (int)(new
              DateTimeImmutable(date('Y-m-d')))->diff(new
              DateTimeImmutable($b['check_out']))->days ?> day(s)<?php
              elseif($b['status']==='confirmed' && $b['check_in'] >
              date('Y-m-d')): ?>Upcoming<?php else: ?>—<?php endif; ?>
            </td>
            <td>₱<?=number_format($b['total_amount'],2)?></td>
            <td>
              <span class="badge <?=e($b['status'])?>"
                ><?=e(ucfirst($b['status']))?></span
              >
            </td>
            <td>
              <a
                class="btn btn-outline btn-sm"
                href="<?=url('booking/view.php?id='.$b['id'])?>"
                >View</a
              >
            </td>
          </tr>
          <?php endforeach;?>
        </tbody>
      </table>
    </div>
    <?php endif;?>
  </div>
</section>
<?php include '../includes/footer.php';?>
