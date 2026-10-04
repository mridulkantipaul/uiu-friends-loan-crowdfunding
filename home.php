<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$u = current_user();
$active = 'home.php';

$filter = $_GET['filter'] ?? 'all';

// Loan posts — every approved post is visible to every logged-in user
// (funded/closed posts stay in the feed with a status badge rather than disappearing)
$loanStmt = $pdo->prepare("SELECT lp.*, u.full_name, u.department, u.batch, u.trust_score, u.uiu_id_verified, u.avatar
    FROM loan_posts lp JOIN users u ON u.id = lp.user_id
    WHERE lp.admin_status='approved'
    ORDER BY lp.created_at DESC");
$loanStmt->execute();
$loanPosts = $loanStmt->fetchAll();

// Campaigns
$campStmt = $pdo->prepare("SELECT c.*, u.full_name, u.department, u.batch
    FROM campaigns c JOIN users u ON u.id = c.organizer_id
    WHERE c.admin_status IN ('verified','pending')
    ORDER BY c.created_at DESC");
$campStmt->execute();
$campaigns = $campStmt->fetchAll();

// merge feed sorted by created_at, respecting filter
$feed = [];
if ($filter === 'all' || $filter === 'loans' || $filter === 'verified') {
    foreach ($loanPosts as $p) {
        if ($filter === 'verified' && !$p['uiu_id_verified']) continue;
        $feed[] = ['type' => 'loan', 'time' => $p['created_at'], 'data' => $p];
    }
}
if ($filter === 'all' || $filter === 'crowdfunding' || $filter === 'verified') {
    foreach ($campaigns as $c) {
        if ($filter === 'verified' && $c['admin_status'] !== 'verified') continue;
        $feed[] = ['type' => 'campaign', 'time' => $c['created_at'], 'data' => $c];
    }
}
usort($feed, fn($a, $b) => strtotime($b['time']) <=> strtotime($a['time']));

// sidebar widgets
$vcamps = array_filter($campaigns, fn($c) => $c['admin_status'] === 'verified');
$topLenders = $pdo->query("SELECT u.full_name, u.avatar, u.trust_score, COALESCE(SUM(t.amount),0) lent, COUNT(DISTINCT a.id) deals
    FROM users u
    LEFT JOIN agreements a ON a.lender_id = u.id
    LEFT JOIN transactions t ON t.payee_id = u.id
    GROUP BY u.id HAVING deals > 0 ORDER BY lent DESC LIMIT 3")->fetchAll();
if (!$topLenders) {
    $topLenders = $pdo->query("SELECT full_name, avatar, trust_score, 0 lent, 0 deals FROM users WHERE role='user' ORDER BY trust_score DESC LIMIT 3")->fetchAll();
}

$page_title = 'Home';
include __DIR__ . '/includes/header.php';
?>
<div class="app-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main-area">
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <div class="content-wrap">
      <div class="content-main">

        <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

        <div class="card">
          <form class="ask-box" action="search.php" method="get">
            <img src="<?= e($u['avatar']) ?>" alt="">
            <input type="text" name="q" placeholder="What do you need help with today?">
          </form>
          <div class="ask-actions">
            <a href="loan_posts.php?new=1" class="btn btn-primary"><i class="fa-solid fa-hand-holding-dollar"></i> Request a Loan</a>
            <a href="crowdfunding.php?new=1" class="btn btn-outline"><i class="fa-solid fa-bullseye"></i> Start a Crowdfunding Campaign</a>
          </div>
        </div>

        <div class="filter-tabs">
          <a href="?filter=all" class="<?= $filter === 'all' ? 'active' : '' ?>">All</a>
          <a href="?filter=loans" class="<?= $filter === 'loans' ? 'active' : '' ?>">Loan Requests</a>
          <a href="?filter=crowdfunding" class="<?= $filter === 'crowdfunding' ? 'active' : '' ?>">Crowdfunding</a>
          <a href="?filter=verified" class="<?= $filter === 'verified' ? 'active' : '' ?>">Verified Only</a>
        </div>

        <?php if (!$feed): ?>
          <div class="card empty-state"><i class="fa-regular fa-folder-open"></i><p>Nothing to show here yet.</p></div>
        <?php endif; ?>

        <?php foreach ($feed as $item): ?>
          <?php if ($item['type'] === 'loan'): $p = $item['data']; ?>
            <div class="card post-card">
              <div class="post-head">
                <div class="post-user">
                  <img src="<?= e($p['avatar']) ?>" alt="">
                  <div>
                    <div class="name"><?= e($p['full_name']) ?> <?= $p['uiu_id_verified'] ? '<span class="badge-verified"><i class="fa-solid fa-circle-check"></i> Verified</span>' : '' ?></div>
                    <div class="meta"><?= e($p['department']) ?> · Batch <?= e($p['batch']) ?> · <i class="fa-solid fa-star" style="color:#f97316"></i> <?= number_format($p['trust_score'],1) ?></div>
                  </div>
                </div>
                <div class="post-time"><?= time_ago($p['created_at']) ?></div>
              </div>
              <div class="post-amount"><?= taka($p['amount']) ?> <span>needed</span></div>
              <div class="post-desc"><?= nl2br(e($p['description'])) ?></div>
              <div class="tag-row">
                <?php if ($p['emi_ok']): ?><span class="tag">EMI OK</span><?php endif; ?>
                <span class="tag"><?= e($p['repay_period_text']) ?></span>
                <span class="tag">Purpose: <?= e($p['purpose']) ?></span>
                <?php if ($p['status'] === 'funded'): ?><span class="tag" style="background:#f0fdf4;color:#15803d;">Funded</span>
                <?php elseif ($p['status'] === 'closed'): ?><span class="tag" style="background:#f3f4f6;color:#52525b;">Closed</span><?php endif; ?>
              </div>
              <div class="post-actions">
                <?php if ($p['status'] === 'open' && $p['user_id'] != $u['id']): ?>
                  <a href="loan_details.php?id=<?= $p['id'] ?>" class="btn btn-primary"><i class="fa-solid fa-hand-holding-dollar"></i> Offer</a>
                <?php else: ?>
                  <a href="loan_details.php?id=<?= $p['id'] ?>" class="btn btn-outline"><i class="fa-regular fa-eye"></i> View Details</a>
                <?php endif; ?>
                <a href="messages.php?to=<?= $p['user_id'] ?>&post=<?= $p['id'] ?>" class="btn btn-outline"><i class="fa-regular fa-comment"></i> Message</a>
              </div>
            </div>
          <?php else: $c = $item['data']; $pct = min(100, round($c['raised_amount'] / max(1,$c['goal_amount']) * 100)); ?>
            <div class="card campaign-mini">
              <div class="top">
                <span><?= $c['admin_status']==='verified' ? '<span class="badge-verified"><i class="fa-solid fa-circle-check"></i> Verified</span>' : '<span class="badge-pending"><i class="fa-solid fa-clock"></i> Pending Verification</span>' ?></span>
                <span><?= time_ago($c['created_at']) ?></span>
              </div>
              <h4><?= e($c['title']) ?></h4>
              <div class="organizer">Organized by <?= e($c['full_name']) ?> (<?= e($c['department']) ?> '<?= substr($c['batch'],-2) ?>)</div>
              <div class="progress-bar"><div style="width:<?= $pct ?>%"></div></div>
              <div class="campaign-stats">
                <span><strong><?= taka($c['raised_amount']) ?></strong> raised of <?= taka($c['goal_amount']) ?></span>
                <span><?= $pct ?>% funded</span>
              </div>
              <div class="post-actions">
                <a href="campaign_details.php?id=<?= $c['id'] ?>" class="btn btn-primary"><i class="fa-solid fa-heart"></i> Donate</a>
                <a href="campaign_details.php?id=<?= $c['id'] ?>#ledger" class="btn btn-outline">View Ledger</a>
              </div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>

      </div>

      <div class="content-side">
        <div class="card">
          <h3>Verified Campaigns</h3>
          <ul class="widget-list">
            <?php foreach ($vcamps as $c): $pct = min(100, round($c['raised_amount'] / max(1,$c['goal_amount']) * 100)); ?>
              <li><a href="campaign_details.php?id=<?= $c['id'] ?>"><?= e($c['title']) ?></a><span class="pct"><?= $pct ?>%</span></li>
            <?php endforeach; ?>
            <?php if (!$vcamps): ?><li style="color:var(--text-muted)">No verified campaigns yet.</li><?php endif; ?>
          </ul>
        </div>

        <div class="card">
          <h3>Top Rated Lenders</h3>
          <?php foreach ($topLenders as $l): ?>
            <div class="lender-item">
              <img src="<?= e($l['avatar']) ?>" alt="">
              <div>
                <div class="name"><?= e($l['full_name']) ?></div>
                <div class="sub">Lent <?= taka($l['lent']) ?> · <?= $l['deals'] ?> deals</div>
              </div>
              <div class="rating"><i class="fa-solid fa-star"></i> <?= number_format($l['trust_score'],1) ?></div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="safety-box">
          <i class="fa-solid fa-shield-halved"></i>
          <h4>Stay Safe & Secure</h4>
          <p>We verify every student ID and use digital agreements to protect you from fraud. Never share OTPs.</p>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
