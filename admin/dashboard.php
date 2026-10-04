<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$admin = current_admin();
$active = 'dashboard.php';

$q = fn($sql) => $pdo->query($sql)->fetch()['c'];

$totalUsers      = $q("SELECT COUNT(*) c FROM users WHERE role='user'");
$nidVerified     = $q("SELECT COUNT(*) c FROM users WHERE role='user' AND nid_status='verified'");
$nidPending      = $q("SELECT COUNT(*) c FROM users WHERE role='user' AND nid_status='pending'");
$totalLoanPosts  = $q("SELECT COUNT(*) c FROM loan_posts");
$pendingLoanPosts= $q("SELECT COUNT(*) c FROM loan_posts WHERE admin_status='pending'");
$totalCampaigns  = $q("SELECT COUNT(*) c FROM campaigns");
$pendingCampaigns= $q("SELECT COUNT(*) c FROM campaigns WHERE admin_status='pending'");
$openReports     = $q("SELECT COUNT(*) c FROM reports WHERE status='open'");
$totalReports    = $q("SELECT COUNT(*) c FROM reports");
$activeAgreements= $q("SELECT COUNT(*) c FROM agreements WHERE status='active'");
$disputed        = $q("SELECT COUNT(*) c FROM agreements WHERE status='disputed'");
$totalVolume     = $pdo->query("SELECT COALESCE(SUM(amount),0) s FROM transactions")->fetch()['s'];
$totalDonations  = $pdo->query("SELECT COALESCE(SUM(amount),0) s FROM donations")->fetch()['s'];

$recentUsers = $pdo->query("SELECT * FROM users WHERE role='user' ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recentTx = $pdo->query("SELECT t.*, u1.full_name payer, u2.full_name payee FROM transactions t
    JOIN users u1 ON u1.id=t.payer_id JOIN users u2 ON u2.id=t.payee_id ORDER BY t.created_at DESC LIMIT 6")->fetchAll();
$recentReports = $pdo->query("SELECT r.*, ru.full_name reporter, tu.full_name reported FROM reports r
    JOIN users ru ON ru.id=r.reporter_id LEFT JOIN users tu ON tu.id=r.reported_user_id
    ORDER BY (r.status='open') DESC, r.created_at DESC LIMIT 5")->fetchAll();
$cats = report_categories();

$page_title = 'Admin Dashboard';
$base = '../';
$use_admin_css = true;
include __DIR__ . '/../includes/header.php';
?>
<div class="admin-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1>Dashboard</h1>
      <div class="mini-user"><img src="<?= e($admin['avatar']) ?>"><span><?= e($admin['full_name']) ?></span></div>
    </div>
    <div class="admin-content">

      <!-- Row 1: people & content — each icon is followed by what it means -->
      <div class="kpi-row">
        <a href="users.php" class="kpi-card">
          <div class="kpi-head"><div class="icon" style="background:#fff3e8;color:#f97316;"><i class="fa-solid fa-users"></i></div><div class="kpi-title">Total Students</div></div>
          <div class="num"><?= $totalUsers ?></div>
          <div class="lbl"><?= $nidVerified ?> with verified NID</div>
        </a>
        <a href="users.php?nid=pending" class="kpi-card">
          <div class="kpi-head"><div class="icon" style="background:#fef9c3;color:#ca8a04;"><i class="fa-solid fa-address-card"></i></div><div class="kpi-title">NID Awaiting Review</div></div>
          <div class="num"><?= $nidPending ?></div>
          <div class="lbl">students waiting to be verified</div>
        </a>
        <a href="loan_posts.php" class="kpi-card">
          <div class="kpi-head"><div class="icon" style="background:#eff6ff;color:#2563eb;"><i class="fa-solid fa-hand-holding-dollar"></i></div><div class="kpi-title">Loan Requests</div></div>
          <div class="num"><?= $totalLoanPosts ?></div>
          <div class="lbl"><?= $pendingLoanPosts ?> pending review</div>
        </a>
        <a href="campaigns.php" class="kpi-card">
          <div class="kpi-head"><div class="icon" style="background:#f0fdf4;color:#16a34a;"><i class="fa-solid fa-bullseye"></i></div><div class="kpi-title">Crowdfunding Campaigns</div></div>
          <div class="num"><?= $totalCampaigns ?></div>
          <div class="lbl"><?= $pendingCampaigns ?> pending verification</div>
        </a>
      </div>

      <!-- Row 2: risk & money -->
      <div class="kpi-row">
        <a href="reports.php" class="kpi-card">
          <div class="kpi-head"><div class="icon" style="background:#fef2f2;color:#dc2626;"><i class="fa-solid fa-flag"></i></div><div class="kpi-title">Open Complaints</div></div>
          <div class="num"><?= $openReports ?></div>
          <div class="lbl">of <?= $totalReports ?> total reports</div>
        </a>
        <a href="reports.php" class="kpi-card">
          <div class="kpi-head"><div class="icon" style="background:#f5f3ff;color:#7c3aed;"><i class="fa-solid fa-file-signature"></i></div><div class="kpi-title">Active Loan Agreements</div></div>
          <div class="num"><?= $activeAgreements ?></div>
          <div class="lbl"><?= $disputed ?> disputed</div>
        </a>
        <a href="transactions.php" class="kpi-card">
          <div class="kpi-head"><div class="icon" style="background:#fff3e8;color:#f97316;"><i class="fa-solid fa-sack-dollar"></i></div><div class="kpi-title">Loan Payment Volume</div></div>
          <div class="num"><?= taka($totalVolume) ?></div>
          <div class="lbl">total repayments processed</div>
        </a>
        <a href="transactions.php" class="kpi-card">
          <div class="kpi-head"><div class="icon" style="background:#fdf2f8;color:#db2777;"><i class="fa-solid fa-heart"></i></div><div class="kpi-title">Crowdfunding Raised</div></div>
          <div class="num"><?= taka($totalDonations) ?></div>
          <div class="lbl">total donations received</div>
        </a>
      </div>

      <div class="admin-table-card" style="margin-bottom:20px;">
        <div class="head"><h3><i class="fa-solid fa-flag" style="color:#dc2626;"></i> Latest Complaints</h3><a href="reports.php" class="link-btn">View all →</a></div>
        <table class="admin-table">
          <tr><th>Issue</th><th>Reported by</th><th>Against</th><th>Status</th><th>Date</th><th></th></tr>
          <?php foreach ($recentReports as $r): ?>
          <tr>
            <td><strong><?= e($r['reason']) ?></strong><div style="font-size:11.5px;color:var(--text-muted);"><?= e($cats[$r['category']] ?? $r['category']) ?></div></td>
            <td><?= e($r['reporter']) ?></td>
            <td><?= $r['reported'] ? e($r['reported']) : '—' ?></td>
            <td><?= report_status_pill($r['status']) ?></td>
            <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
            <td><a href="view_report.php?id=<?= $r['id'] ?>" class="link-btn">Review</a></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$recentReports): ?><tr><td colspan="6" style="color:var(--text-muted);">No complaints filed yet.</td></tr><?php endif; ?>
        </table>
      </div>

      <div class="admin-table-card" style="margin-bottom:20px;">
        <div class="head"><h3>Recently Joined Students</h3><a href="users.php" class="link-btn">View all →</a></div>
        <table class="admin-table">
          <tr><th>Student</th><th>Department</th><th>Trust Score</th><th>NID</th><th>Credentials</th></tr>
          <?php foreach ($recentUsers as $ru): ?>
          <tr>
            <td><a href="view_user.php?id=<?= $ru['id'] ?>"><div class="mini-user"><img src="<?= e($ru['avatar']) ?>"><div><div class="n"><?= e($ru['full_name']) ?></div><div class="s"><?= e($ru['email']) ?></div></div></div></a></td>
            <td><?= e($ru['department']) ?> '<?= substr($ru['batch'],-2) ?></td>
            <td><i class="fa-solid fa-star" style="color:#f97316;"></i> <?= number_format($ru['trust_score'],1) ?></td>
            <td><?= nid_status_pill($ru['nid_status']) ?></td>
            <td><a href="view_user.php?id=<?= $ru['id'] ?>" class="link-btn">Review</a></td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>

      <div class="admin-table-card">
        <div class="head"><h3>Recent Transactions</h3><a href="transactions.php" class="link-btn">View all →</a></div>
        <table class="admin-table">
          <tr><th>Txn Ref</th><th>Payer → Payee</th><th>Amount</th><th>Method</th><th>Date</th></tr>
          <?php foreach ($recentTx as $t): ?>
          <tr>
            <td><?= e($t['txn_ref']) ?></td>
            <td><?= e($t['payer']) ?> → <?= e($t['payee']) ?></td>
            <td><?= taka($t['amount']) ?></td>
            <td><span class="pill pill-gray"><?= ucfirst($t['payment_method']) ?></span></td>
            <td><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$recentTx): ?><tr><td colspan="5" style="color:var(--text-muted);">No transactions yet.</td></tr><?php endif; ?>
        </table>
      </div>

    </div>
  </div>
</div>
</body>
</html>
