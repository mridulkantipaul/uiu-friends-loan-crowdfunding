<?php
/** Expects $active to be set. $admin is optional (used for the identity chip). */
global $pdo;
if (!isset($admin) || !$admin) { $admin = current_admin(); }

// live counters for the nav badges
$navPendingPosts = (int)$pdo->query("SELECT COUNT(*) c FROM loan_posts WHERE admin_status='pending'")->fetch()['c'];
$navPendingCamps = (int)$pdo->query("SELECT COUNT(*) c FROM campaigns WHERE admin_status='pending'")->fetch()['c'];
$navUnverified   = (int)$pdo->query("SELECT COUNT(*) c FROM users WHERE role='user' AND nid_status='pending'")->fetch()['c'];
$navOpenReports  = (int)$pdo->query("SELECT COUNT(*) c FROM reports WHERE status='open'")->fetch()['c'];

$nav = [
    'dashboard.php'    => ['icon' => 'fa-chart-line',         'label' => 'Dashboard',          'badge' => 0],
    'users.php'        => ['icon' => 'fa-users',              'label' => 'Manage Users',       'badge' => $navUnverified],
    'loan_posts.php'   => ['icon' => 'fa-hand-holding-dollar','label' => 'Loan Posts',         'badge' => $navPendingPosts],
    'campaigns.php'    => ['icon' => 'fa-bullseye',           'label' => 'Campaigns',          'badge' => $navPendingCamps],
    'transactions.php' => ['icon' => 'fa-money-bill-transfer','label' => 'Transactions',       'badge' => 0],
    'reports.php'      => ['icon' => 'fa-flag',               'label' => 'Reports & Disputes', 'badge' => $navOpenReports],
];
?>
<aside class="admin-sidebar">

  <!-- Brand block -->
  <a href="dashboard.php" class="admin-brand">
    <div class="admin-brand-mark"><i class="fa-solid fa-shield-halved"></i></div>
    <div class="admin-brand-text">
      <div class="admin-brand-name">UIU <span>Friends</span></div>
      <div class="admin-brand-sub">Loan &amp; Crowdfunding</div>
    </div>
  </a>

  <!-- Console badge (replaces the old plain "ADMIN PANEL" text) -->
  <div class="admin-console-badge">
    <i class="fa-solid fa-sliders"></i>
    <span>Administrator Console</span>
  </div>

  <nav class="admin-nav">
    <?php foreach ($nav as $file => $item): ?>
      <a href="<?= $file ?>" class="<?= $active === $file ? 'active' : '' ?>">
        <i class="fa-solid <?= $item['icon'] ?>"></i>
        <span><?= $item['label'] ?></span>
        <?php if ($item['badge'] > 0): ?>
          <span class="admin-nav-badge"><?= $item['badge'] ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <!-- Signed-in admin identity + logout -->
  <div class="admin-userbox">
    <img src="<?= e($admin['avatar'] ?? '../assets/img/default-avatar.svg') ?>" alt="">
    <div class="admin-userbox-text">
      <div class="n"><?= e($admin['full_name'] ?? 'Administrator') ?></div>
      <div class="s">Signed in as admin</div>
    </div>
  </div>
  <a href="logout.php" class="admin-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  <a href="../home.php" target="_blank" class="admin-visit"><i class="fa-solid fa-arrow-up-right-from-square"></i> Visit live site</a>
</aside>
