<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$admin = current_admin();
$active = 'reports.php';

$cats = report_categories();
$status = $_GET['status'] ?? 'all';
if (!in_array($status, ['all','open','reviewing','resolved','dismissed'], true)) $status = 'all';

$counts = ['all' => 0, 'open' => 0, 'reviewing' => 0, 'resolved' => 0, 'dismissed' => 0];
foreach ($pdo->query("SELECT status, COUNT(*) c FROM reports GROUP BY status")->fetchAll() as $row) {
    $counts[$row['status']] = (int)$row['c'];
    $counts['all'] += (int)$row['c'];
}

$sql = "SELECT r.*, ru.full_name reporter_name, ru.email reporter_email, ru.avatar reporter_avatar,
               tu.full_name reported_name, tu.id reported_uid
        FROM reports r
        JOIN users ru ON ru.id = r.reporter_id
        LEFT JOIN users tu ON tu.id = r.reported_user_id";
$params = [];
if ($status !== 'all') { $sql .= " WHERE r.status = ?"; $params[] = $status; }
$sql .= " ORDER BY FIELD(r.status,'open','reviewing','resolved','dismissed'), r.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

$disputedAgreements = $pdo->query("SELECT a.*, bu.full_name borrower_name, lu.full_name lender_name FROM agreements a
    JOIN users bu ON bu.id=a.borrower_id JOIN users lu ON lu.id=a.lender_id WHERE a.status='disputed'")->fetchAll();

$page_title = 'Reports & Disputes';
$base = '../';
$use_admin_css = true;
include __DIR__ . '/../includes/header.php';
?>
<div class="admin-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar"><h1>Reports &amp; Disputes</h1><div class="mini-user"><img src="<?= e($admin['avatar']) ?>"><span><?= e($admin['full_name']) ?></span></div></div>
    <div class="admin-content">

      <div class="kpi-row">
        <a href="?status=open" class="kpi-card"><div class="kpi-head"><div class="icon" style="background:#fef2f2;color:#dc2626;"><i class="fa-solid fa-circle-exclamation"></i></div><div class="kpi-title">Open</div></div><div class="num"><?= $counts['open'] ?></div><div class="lbl">need your attention</div></a>
        <a href="?status=reviewing" class="kpi-card"><div class="kpi-head"><div class="icon" style="background:#fff7ed;color:#ea580c;"><i class="fa-solid fa-magnifying-glass"></i></div><div class="kpi-title">Under Review</div></div><div class="num"><?= $counts['reviewing'] ?></div><div class="lbl">being investigated</div></a>
        <a href="?status=resolved" class="kpi-card"><div class="kpi-head"><div class="icon" style="background:#f0fdf4;color:#16a34a;"><i class="fa-solid fa-circle-check"></i></div><div class="kpi-title">Resolved</div></div><div class="num"><?= $counts['resolved'] ?></div><div class="lbl">action taken</div></a>
        <a href="?status=all" class="kpi-card"><div class="kpi-head"><div class="icon" style="background:#f3f4f6;color:#52525b;"><i class="fa-solid fa-flag"></i></div><div class="kpi-title">All Reports</div></div><div class="num"><?= $counts['all'] ?></div><div class="lbl"><?= $counts['dismissed'] ?> dismissed</div></a>
      </div>

      <div class="admin-table-card" style="margin-bottom:22px;">
        <div class="head">
          <h3>Complaints from students <?= $status !== 'all' ? '— ' . ucfirst($status) : '' ?> (<?= count($reports) ?>)</h3>
          <?php if ($status !== 'all'): ?><a href="reports.php" class="link-btn">Show all</a><?php endif; ?>
        </div>
        <table class="admin-table">
          <tr><th>#</th><th>Issue</th><th>Reported by</th><th>Against</th><th>Related to</th><th>Status</th><th>Date</th><th></th></tr>
          <?php foreach ($reports as $r): ?>
          <tr>
            <td>#<?= $r['id'] ?></td>
            <td style="max-width:260px;"><strong><?= e($r['reason']) ?></strong>
              <div style="font-size:11.5px;color:var(--text-muted);"><span class="pill pill-gray"><?= e($cats[$r['category']] ?? $r['category']) ?></span></div></td>
            <td><div class="mini-user"><img src="<?= e($r['reporter_avatar']) ?>"><div><div class="n"><?= e($r['reporter_name']) ?></div><div class="s"><?= e($r['reporter_email']) ?></div></div></div></td>
            <td><?= $r['reported_uid'] ? '<a href="view_user.php?id=' . $r['reported_uid'] . '" class="link-btn">' . e($r['reported_name']) . '</a>' : '<span style="color:var(--text-muted);">—</span>' ?></td>
            <td><?= $r['related_type'] !== 'other' ? ucwords(str_replace('_',' ',$r['related_type'])) . ($r['related_id'] ? ' #' . $r['related_id'] : '') : '—' ?></td>
            <td><?= report_status_pill($r['status']) ?></td>
            <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
            <td><a href="view_report.php?id=<?= $r['id'] ?>" class="btn btn-primary btn-sm">Review</a></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$reports): ?><tr><td colspan="8" style="color:var(--text-muted);">No reports here. 🎉</td></tr><?php endif; ?>
        </table>
      </div>

      <div class="admin-table-card">
        <div class="head"><h3>Disputed Agreements (<?= count($disputedAgreements) ?>)</h3></div>
        <table class="admin-table">
          <tr><th>Borrower</th><th>Lender</th><th>Principal</th><th>Status</th></tr>
          <?php foreach ($disputedAgreements as $a): ?>
          <tr><td><?= e($a['borrower_name']) ?></td><td><?= e($a['lender_name']) ?></td><td><?= taka($a['principal']) ?></td><td><span class="pill pill-red">Disputed</span></td></tr>
          <?php endforeach; ?>
          <?php if (!$disputedAgreements): ?><tr><td colspan="4" style="color:var(--text-muted);">No disputed agreements.</td></tr><?php endif; ?>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
