<?php /** Expects $u (current user) */ ?>
<header class="topbar">
  <div class="topbar-welcome">
    <img src="<?= e($u['avatar']) ?>" alt="">
    <div>
      <h1>Welcome back, <?= e(explode(' ', $u['full_name'])[0]) ?> (<?= e($u['department']) ?>, Batch <?= e($u['batch']) ?>)</h1>
      <div class="meta">
        <?= $u['uiu_id_verified'] ? '<span class="badge-verified"><i class="fa-solid fa-circle-check"></i> UIU ID Verified</span>' : '<span class="badge-pending"><i class="fa-solid fa-clock"></i> Verification Pending</span>' ?>
        <span class="trust-chip"><i class="fa-solid fa-star"></i> <?= number_format($u['trust_score'],1) ?> Trust Score</span>
      </div>
    </div>
  </div>
  <div class="topbar-actions">
    <form action="search.php" method="get" class="search-box" style="margin:0;">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" name="q" placeholder="Search loans &amp; campaigns..." value="<?= e($_GET['q'] ?? '') ?>">
    </form>
    <a href="messages.php" class="icon-btn"><i class="fa-regular fa-bell"></i><?php if (($unread ?? 0) > 0): ?><span class="dot"></span><?php endif; ?></a>
    <a href="security.php" class="icon-btn"><i class="fa-solid fa-gear"></i></a>
  </div>
</header>
<?php if (!nid_verified($u) && ($active ?? '') !== 'security.php'): ?>
<div class="nid-banner">
  <i class="fa-solid fa-address-card"></i>
  <div>
    <strong>NID verification <?= $u['nid_status'] === 'pending' ? 'is under review' : ($u['nid_status'] === 'rejected' ? 'was rejected' : 'is required') ?>.</strong>
    <?= $u['nid_status'] === 'pending'
        ? 'You can borrow, lend and start campaigns as soon as an admin approves it.'
        : 'Submit your National ID to request loans, make offers or start a campaign.' ?>
  </div>
  <?php if ($u['nid_status'] !== 'pending'): ?><a href="security.php#nid" class="btn btn-primary btn-sm">Verify NID</a><?php endif; ?>
</div>
<?php endif; ?>
