<?php
require '../config/database.php';
require '../includes/functions.php';
require_staff_or_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_staff()) { http_response_code(403); exit('Only reception staff can approve or decline bookings here.'); }
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    if (!in_array($decision, ['confirmed', 'cancelled'], true)) {
        flash('error', 'Invalid booking decision.');
    } else {
        [$ok, $message] = change_booking_status($pdo, $id, $decision, 'pending');
        flash($ok ? 'success' : 'error', $ok
            ? ($decision === 'confirmed' ? 'Booking approved. Room availability has been recalculated.' : 'Booking declined. The dates are no longer reserved by this request.')
            : $message);
    }
    header('Location: ' . url('booking/allBookings.php?status=pending'));
    exit;
}

$counts = $pdo->query("SELECT COUNT(*) total,
    SUM(status='pending') pending,
    SUM(status='confirmed') approved,
    SUM(status='cancelled') declined,
    SUM(status='completed') completed FROM bookings")->fetch() ?: [];
foreach (['total','pending','approved','declined','completed'] as $key) $counts[$key] = (int)($counts[$key] ?? 0);

$filter = $_GET['status'] ?? 'all';
$validFilters = ['all', 'pending', 'confirmed', 'cancelled', 'completed'];
if (!in_array($filter, $validFilters, true)) $filter = 'all';
$sql = "SELECT b.*, u.name AS guest, u.email, r.name AS room_name
        FROM bookings b JOIN users u ON u.id=b.user_id JOIN rooms r ON r.id=b.room_id";
$params = [];
if ($filter !== 'all') { $sql .= ' WHERE b.status=?'; $params[] = $filter; }
$sql .= " ORDER BY CASE WHEN b.status='pending' THEN 0 ELSE 1 END, b.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $bookings = $stmt->fetchAll();
$page_title = 'All Bookings';
include '../includes/header.php';
?>
<div class="workspace">
  <aside class="workspace-sidebar">
    <div class="side-label"><?= is_admin() ? 'ADMINISTRATION' : 'RECEPTION DESK' ?></div>
    <a href="<?=url(is_admin() ? 'admin/index.php' : 'staff/index.php')?>">▦ &nbsp; Overview</a>
    <a class="active" href="<?=url('booking/allBookings.php')?>">▤ &nbsp; All bookings</a>
    <?php if (is_admin()): ?>
      <a href="<?=url('admin/rooms/index.php')?>">▣ &nbsp; Room inventory</a>
      <a href="<?=url('admin/users/index.php')?>">♙ &nbsp; User management</a>
    <?php else: ?>
      <a href="<?=url('staff/index.php#available-rooms')?>">▣ &nbsp; Available rooms</a>
    <?php endif; ?>
    <div class="sidebar-note"><strong>Booking overview</strong><p>Review reservation status and keep track of pending requests. Room availability is checked against reservation dates.</p></div>
  </aside>
  <section class="workspace-main">
    <div class="workspace-heading">
      <div><div class="eyebrow" style="color:var(--brand)"><?=is_admin() ? 'ADMINISTRATION' : 'FRONT DESK'?></div><h1>All bookings</h1><p class="muted">Track pending requests, approved stays, and declined reservations.</p></div>
      <a class="btn btn-outline" href="<?=url(is_admin() ? 'admin/index.php' : 'staff/index.php')?>">← Back to overview</a>
    </div>
    <div class="stats-dashboard">
      <a class="metric-card" href="<?=url('booking/allBookings.php?status=pending')?>"><span class="metric-icon amber">◷</span><span class="metric-label">Pending approval</span><strong><?=$counts['pending']?></strong><small>Awaiting a decision</small></a>
      <a class="metric-card" href="<?=url('booking/allBookings.php?status=confirmed')?>"><span class="metric-icon green">✓</span><span class="metric-label">Approved bookings</span><strong><?=$counts['approved']?></strong><small>Confirmed reservations</small></a>
      <a class="metric-card" href="<?=url('booking/allBookings.php?status=cancelled')?>"><span class="metric-icon violet">×</span><span class="metric-label">Declined / cancelled</span><strong><?=$counts['declined']?></strong><small>Not holding room dates</small></a>
      <a class="metric-card" href="<?=url('booking/allBookings.php?status=all')?>"><span class="metric-icon blue">▤</span><span class="metric-label">All bookings</span><strong><?=$counts['total']?></strong><small><?=$counts['completed']?> completed stays</small></a>
    </div>
    <div class="filter-tabs">
      <?php foreach (['all'=>'All bookings','pending'=>'Pending','confirmed'=>'Approved','cancelled'=>'Declined / cancelled','completed'=>'Completed'] as $key=>$label): ?>
        <a class="filter-tab <?=$filter===$key?'active':''?>" href="<?=url('booking/allBookings.php?status='.$key)?>"><?=$label?></a>
      <?php endforeach; ?>
    </div>
    <section class="panel"><div class="panel-heading"><div><h2><?=e(['all'=>'All bookings','pending'=>'Pending bookings','confirmed'=>'Approved bookings','cancelled'=>'Declined / cancelled bookings','completed'=>'Completed bookings'][$filter])?></h2><p class="muted"><?=count($bookings)?> record(s) in this view</p></div></div>
      <div class="table-wrap clean-table"><table class="table"><thead><tr><th>Reference</th><th>Customer</th><th>Room</th><th>Stay dates</th><th>Booked on</th><th>Guests</th><th>Total</th><th>Status / action</th></tr></thead><tbody>
      <?php if (!$bookings): ?><tr><td colspan="8" class="empty">No bookings found for this status.</td></tr><?php endif; ?>
      <?php foreach ($bookings as $b): ?><tr>
        <td><strong><?=e($b['reference_no'])?></strong></td>
        <td><?=e($b['guest'])?><small class="table-sub"><?=e($b['email'])?></small></td>
        <td><?=e($b['room_name'])?></td>
        <td><?=e($b['check_in'])?><small class="table-sub">to <?=e($b['check_out'])?></small></td>
        <td><?=e(date('M j, Y', strtotime($b['created_at'])))?><small class="table-sub"><?=e(date('g:i A', strtotime($b['created_at'])))?></small></td>
        <td><?=e($b['guests'])?></td><td>₱<?=number_format((float)$b['total_amount'],2)?></td>
        <td><?php if (is_staff() && $b['status']==='pending'): ?><div class="actions">
          <form method="post" data-confirm="Approve this booking? The system will verify the room is still available for these dates." data-confirm-title="Approve booking"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="id" value="<?=$b['id']?>"><input type="hidden" name="decision" value="confirmed"><button class="btn btn-primary btn-sm">Approve</button></form>
          <form method="post" data-confirm="Decline this booking request?" data-confirm-title="Decline booking"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="id" value="<?=$b['id']?>"><input type="hidden" name="decision" value="cancelled"><button class="btn btn-outline btn-sm">Decline</button></form>
        </div><?php else: ?><span class="badge <?=e($b['status'])?>"><?=e($b['status']==='confirmed'?'Approved':ucfirst($b['status']))?></span><?php if (is_admin()): ?><small class="table-sub"><a href="<?=url('admin/bookings/index.php')?>">Manage status / payment</a></small><?php endif; ?><?php endif; ?></td>
      </tr><?php endforeach; ?>
      </tbody></table></div>
    </section>
  </section>
</div>
<?php include '../includes/footer.php'; ?>
