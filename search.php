<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$u = current_user();
$active = 'home.php';

$q    = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all';   // all | loans | campaigns

$loanResults = [];
$campResults = [];

if ($q !== '') {
    $like = "%$q%";

    if ($type === 'all' || $type === 'loans') {
        $stmt = $pdo->prepare("SELECT lp.*, u.full_name, u.department, u.batch, u.trust_score, u.uiu_id_verified, u.avatar
            FROM loan_posts lp JOIN users u ON u.id = lp.user_id
            WHERE lp.admin_status='approved'
              AND (lp.purpose LIKE ? OR lp.description LIKE ? OR u.full_name LIKE ? OR lp.repay_period_text LIKE ?)
            ORDER BY lp.created_at DESC");
        $stmt->execute([$like, $like, $like, $like]);
        $loanResults = $stmt->fetchAll();
    }

    if ($type === 'all' || $type === 'campaigns') {
        $stmt = $pdo->prepare("SELECT c.*, u.full_name, u.department, u.batch, u.uiu_id_verified,
            (SELECT COUNT(*) FROM donations WHERE campaign_id=c.id) donor_count
            FROM campaigns c JOIN users u ON u.id = c.organizer_id
            WHERE c.admin_status IN ('verified','pending')
              AND (c.title LIKE ? OR c.description LIKE ? OR u.full_name LIKE ?)
            ORDER BY c.created_at DESC");
        $stmt->execute([$like, $like, $like]);
        $campResults = $stmt->fetchAll();
    }
}

$total = count($loanResults) + count($campResults);

$page_title = 'Search';
include __DIR__ . '/includes/header.php';
?>
<div class="app-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main-area">
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <div class="content-wrap">
      <div class="content-main">

        <div class="card">
          <h3><i class="fa-solid fa-magnifying-glass"></i> Search</h3>
          <form method="get" action="search.php">
            <div class="form-row">
              <div class="form-group" style="flex:2;">
                <label>Keyword</label>
                <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search loan requests and crowdfunding campaigns...">
              </div>
              <div class="form-group">
                <label>Type</label>
                <select name="type">
                  <option value="all" <?= $type==='all'?'selected':'' ?>>Loans &amp; Campaigns</option>
                  <option value="loans" <?= $type==='loans'?'selected':'' ?>>Loan Requests only</option>
                  <option value="campaigns" <?= $type==='campaigns'?'selected':'' ?>>Crowdfunding only</option>
                </select>
              </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
          </form>
        </div>

        <?php if ($q === ''): ?>
          <div class="card empty-state">
            <i class="fa-solid fa-magnifying-glass"></i>
            <p>Enter a keyword above to search loan requests and crowdfunding campaigns.</p>
          </div>
        <?php else: ?>
          <p style="font-size:13.5px;color:var(--text-muted);">
            <strong><?= $total ?></strong> result<?= $total===1?'':'s' ?> for "<?= e($q) ?>"
            — <?= count($loanResults) ?> loan request<?= count($loanResults)===1?'':'s' ?>,
            <?= count($campResults) ?> campaign<?= count($campResults)===1?'':'s' ?>.
          </p>
        <?php endif; ?>

        <?php if ($campResults): ?>
          <h3 style="font-size:15px;margin:4px 0 0;"><i class="fa-solid fa-bullseye" style="color:var(--orange)"></i> Crowdfunding Campaigns</h3>
          <?php foreach ($campResults as $c): $pct = min(100, round($c['raised_amount'] / max(1,$c['goal_amount']) * 100)); ?>
            <div class="card campaign-mini">
              <div class="top">
                <span><?= $c['admin_status']==='verified'
                    ? '<span class="badge-verified"><i class="fa-solid fa-circle-check"></i> Verified Campaign</span>'
                    : '<span class="badge-pending"><i class="fa-solid fa-clock"></i> Pending Verification</span>' ?></span>
                <span><?= time_ago($c['created_at']) ?></span>
              </div>
              <h4><?= e($c['title']) ?></h4>
              <div class="organizer">Organized by <?= e($c['full_name']) ?> · <?= e($c['department']) ?> '<?= substr($c['batch'],-2) ?></div>
              <p class="post-desc"><?= e(truncate_text($c['description'], 160)) ?></p>
              <div class="progress-bar"><div style="width:<?= $pct ?>%"></div></div>
              <div class="campaign-stats">
                <span><strong><?= taka($c['raised_amount']) ?></strong> raised of <?= taka($c['goal_amount']) ?></span>
                <span><?= $pct ?>% funded · <?= $c['donor_count'] ?> donors</span>
              </div>
              <div class="post-actions">
                <a href="campaign_details.php?id=<?= $c['id'] ?>" class="btn btn-primary"><i class="fa-solid fa-heart"></i> View &amp; Donate</a>
                <a href="campaign_details.php?id=<?= $c['id'] ?>#ledger" class="btn btn-outline">View Ledger</a>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($loanResults): ?>
          <h3 style="font-size:15px;margin:10px 0 0;"><i class="fa-solid fa-hand-holding-dollar" style="color:var(--orange)"></i> Loan Requests</h3>
          <?php foreach ($loanResults as $p): ?>
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
              <div class="post-desc"><?= e(truncate_text($p['description'], 200)) ?></div>
              <div class="tag-row">
                <?php if ($p['emi_ok']): ?><span class="tag">EMI OK</span><?php endif; ?>
                <span class="tag"><?= e($p['repay_period_text']) ?></span>
                <span class="tag">Purpose: <?= e($p['purpose']) ?></span>
                <?php if ($p['status'] === 'funded'): ?><span class="tag" style="background:#f0fdf4;color:#15803d;">Funded</span><?php endif; ?>
              </div>
              <div class="post-actions">
                <a href="loan_details.php?id=<?= $p['id'] ?>" class="btn btn-primary"><i class="fa-regular fa-eye"></i> View Details</a>
                <a href="messages.php?to=<?= $p['user_id'] ?>&post=<?= $p['id'] ?>" class="btn btn-outline"><i class="fa-regular fa-comment"></i> Message</a>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($q !== '' && $total === 0): ?>
          <div class="card empty-state">
            <i class="fa-regular fa-face-frown"></i>
            <p>No loan requests or campaigns matched "<?= e($q) ?>".<br>Try a different keyword.</p>
          </div>
        <?php endif; ?>

      </div>

      <div class="content-side">
        <div class="safety-box">
          <i class="fa-solid fa-shield-halved"></i>
          <h4>Stay Safe &amp; Secure</h4>
          <p>We verify every student ID and use digital agreements to protect you from fraud. Never share OTPs.</p>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
