<?php
require '../../config/database.php'; require '../../includes/functions.php'; require_staff();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $id = (int)($_POST['id'] ?? 0); $decision = $_POST['decision'] ?? '';
    if (!in_array($decision, ['confirmed','cancelled'], true)) flash('error', 'Invalid booking decision.');
    else {
        [$ok, $message] = change_booking_status($pdo, $id, $decision, 'pending', 'staff');
        flash($ok && strpos($message, 'could not be sent') === false ? 'success' : 'error', $message);
    }
    header('Location: '.url('staff/bookings/index.php')); exit;
}
$filter = $_GET['status'] ?? 'pending'; if (!in_array($filter,['pending','confirmed','cancelled','all'],true)) $filter='pending';
$sql = "SELECT b.*,u.name guest,u.email,r.name room_name FROM bookings b JOIN users u ON u.id=b.user_id JOIN rooms r ON r.id=b.room_id"; $args=[];
if ($filter !== 'all') { $sql .= ' WHERE b.status=?'; $args[]=$filter; }
$sql .= " ORDER BY CASE WHEN b.status='pending' THEN 0 ELSE 1 END,b.created_at ASC"; $st=$pdo->prepare($sql);
$st->execute($args); $bookings=$st->fetchAll(); $page_title='Booking Approvals';
include '../../includes/header.php'; ?>
<div class="workspace">
  <aside class="workspace-sidebar">
    <div class="side-label">RECEPTION DESK</div>
    <a href="<?=url('staff/index.php')?>">▦ &nbsp; Overview</a
    ><a class="active" href="<?=url('staff/bookings/index.php')?>"
      >✓ &nbsp; Booking approvals</a
    ><a href="<?=url('staff/index.php#available-rooms')?>"
      >▣ &nbsp; Available rooms</a
    >
    <div class="sidebar-note">
      <strong>Approval rules</strong>
      <p>
        Approval checks current room availability and prevents overlapping
        confirmed reservations.
      </p>
    </div>
  </aside>
  <section class="workspace-main">
    <div class="workspace-heading">
      <div>
        <div class="eyebrow" style="color: var(--brand)">FRONT DESK</div>
        <h1>Booking approvals</h1>
        <p class="muted">
          Review customer requests and make a reservation decision.
        </p>
      </div>
      <a class="btn btn-outline" href="<?=url('staff/index.php')?>"
        >← Back to overview</a
      >
    </div>
    <div class="filter-tabs">
      <?php foreach(['pending'=>'Pending','confirmed'=>'Approved','cancelled'=>'Declined','all'=>'All
      bookings'] as $key=>$label): ?><a
        class="filter-tab <?=$filter===$key?'active':''?>"
        href="<?=url('staff/bookings/index.php?status='.$key)?>"
        ><?=$label?></a
      ><?php endforeach; ?>
    </div>
    <section class="panel">
      <div class="table-wrap clean-table">
        <table class="table">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Customer</th>
              <th>Room</th>
              <th>Stay dates</th>
              <th>Booked on</th>
              <th>Guests</th>
              <th>Total</th>
              <th>Status / action</th>
            </tr>
          </thead>
          <tbody>
            <?php if(!$bookings): ?>
            <tr>
              <td colspan="8" class="empty">No bookings in this view.</td>
            </tr>
            <?php endif; foreach($bookings as $b): ?>
            <tr>
              <td>
                <strong><?=e($b['reference_no'])?></strong>
              </td>
              <td>
                <?=e($b['guest'])?><small class="table-sub"
                  ><?=e($b['email'])?></small
                >
              </td>
              <td><?=e($b['room_name'])?></td>
              <td>
                <?=e($b['check_in'])?><small class="table-sub"
                  >to <?=e($b['check_out'])?></small
                >
              </td>
              <td>
                <?=e(date('M j, Y',strtotime($b['created_at'])))?><small
                  class="table-sub"
                  ><?=e(date('g:i A',strtotime($b['created_at'])))?></small
                >
              </td>
              <td><?=e($b['guests'])?></td>
              <td>₱<?=number_format($b['total_amount'],2)?></td>
              <td>
                <?php if($b['status']==='pending'): ?>
                <div class="actions">
                  <form
                    method="post"
                    data-confirm="Approve this reservation? The system will recheck the room dates before confirming."
                    data-confirm-title="Approve booking"
                  >
                    <input
                      type="hidden"
                      name="csrf"
                      value="<?=csrf_token()?>"
                    /><input
                      type="hidden"
                      name="id"
                      value="<?=$b['id']?>"
                    /><input
                      type="hidden"
                      name="decision"
                      value="confirmed"
                    /><button class="btn btn-primary btn-sm">Approve</button>
                  </form>
                  <form
                    method="post"
                    data-confirm="Decline this reservation request?"
                    data-confirm-title="Decline booking"
                  >
                    <input
                      type="hidden"
                      name="csrf"
                      value="<?=csrf_token()?>"
                    /><input
                      type="hidden"
                      name="id"
                      value="<?=$b['id']?>"
                    /><input
                      type="hidden"
                      name="decision"
                      value="cancelled"
                    /><button class="btn btn-outline btn-sm">Decline</button>
                  </form>
                </div>
                <?php else: ?><span class="badge <?=e($b['status'])?>"
                  ><?=e(ucfirst($b['status']))?></span
                ><?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</div>
<?php include '../../includes/footer.php'; ?>
