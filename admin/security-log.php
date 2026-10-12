<?php
require '../config/database.php';
require '../includes/functions.php';
require_admin();
$events = $pdo->query("SELECT event_type,user_id,ip_hash,details,created_at FROM
security_events ORDER BY id DESC LIMIT 200")->fetchAll(); $page_title =
'Security Audit Log'; include '../includes/header.php'; ?>
<div class="workspace">
  <aside class="workspace-sidebar">
    <div class="side-label">ADMINISTRATION</div>
    <a href="<?=url('admin/index.php')?>">▦ &nbsp; Overview</a>
    <a href="<?=url('admin/bookings/index.php')?>">▤ &nbsp; All bookings</a>
    <a href="<?=url('admin/rooms/index.php')?>">▣ &nbsp; Room inventory</a>
    <a href="<?=url('admin/users/index.php')?>">♙ &nbsp; User management</a>
    <a class="active" href="<?=url('admin/security-log.php')?>"
      >◉ &nbsp; Security log</a
    >
  </aside>
  <section class="workspace-main">
    <div class="workspace-heading">
      <div>
        <div class="eyebrow" style="color: var(--brand)">SECURITY</div>
        <h1>Security audit log</h1>
        <p class="muted">
          Latest 200 recorded authentication and account-access events. Network
          addresses are stored as hashes.
        </p>
      </div>
    </div>
    <section class="panel">
      <div class="table-wrap clean-table">
        <table class="table">
          <thead>
            <tr>
              <th>Date / time</th>
              <th>Event</th>
              <th>User ID</th>
              <th>Source hash</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$events): ?>
            <tr>
              <td colspan="5" class="empty">
                No security events have been recorded yet.
              </td>
            </tr>
            <?php endif; ?>
            <?php foreach ($events as $event): ?>
            <tr>
              <td><?=e($event['created_at'])?></td>
              <td>
                <strong><?=e($event['event_type'])?></strong>
              </td>
              <td><?=e($event['user_id'] ?? '—')?></td>
              <td>
                <code><?=e(substr($event['ip_hash'], 0, 16))?>…</code>
              </td>
              <td><?=e($event['details'])?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</div>
<?php include '../includes/footer.php'; ?>
