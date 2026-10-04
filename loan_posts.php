<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$u = current_user();
$active = 'loan_posts.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {
    csrf_check();
    $amount   = (float)($_POST['amount'] ?? 0);
    $purpose  = trim($_POST['purpose'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $emi_ok   = isset($_POST['emi_ok']) ? 1 : 0;
    $repay    = trim($_POST['repay_period_text'] ?? '');
    if ($gate = nid_gate($u, 'request a loan')) {
        $formError = $gate;
    } elseif ($amount > 0 && $purpose && $desc && $repay) {
        $stmt = $pdo->prepare("INSERT INTO loan_posts (user_id,amount,purpose,description,emi_ok,repay_period_text) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$u['id'], $amount, $purpose, $desc, $emi_ok, $repay]);
        flash('success', 'Your loan request has been posted.');
        redirect('loan_posts.php');
    } else {
        $formError = 'Please fill in all fields.';
    }
}

$q = trim($_GET['q'] ?? '');
$sql = "SELECT lp.*, u.full_name, u.department, u.batch, u.trust_score, u.uiu_id_verified, u.avatar
        FROM loan_posts lp JOIN users u ON u.id = lp.user_id
        WHERE lp.admin_status='approved'";
$params = [];
if ($q !== '') {
    $sql .= " AND (lp.purpose LIKE ? OR lp.description LIKE ? OR u.full_name LIKE ?)";
    $like = "%$q%";
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY lp.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

$page_title = 'Loan Posts';
include __DIR__ . '/includes/header.php';
?>
<div class="app-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main-area">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <div class="content-wrap">
      <div class="content-main">

        <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

        <?php if (isset($_GET['new']) && ($gate = nid_gate($u, 'request a loan'))): ?>
        <div class="card">
          <h3><i class="fa-solid fa-address-card"></i> NID verification required</h3>
          <div class="alert alert-error"><?= e($gate) ?></div>
          <p style="font-size:13.5px;color:var(--text-muted);margin-top:0;">To keep every loan safe, borrowers must be identified by a verified National ID. This lets lenders trust the request and allows legal action if a borrower does not repay.</p>
          <a href="security.php#nid" class="btn btn-primary"><i class="fa-solid fa-address-card"></i> Verify my NID</a>
        </div>
        <?php elseif (isset($_GET['new'])): ?>
        <div class="card">
          <h3><i class="fa-solid fa-hand-holding-dollar"></i> Request a Loan</h3>
          <?php if (!empty($formError)): ?><div class="alert alert-error"><?= e($formError) ?></div><?php endif; ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="create_post" value="1">
            <div class="form-row">
              <div class="form-group"><label>Amount Needed (৳)</label><input type="number" name="amount" min="100" step="1" required></div>
              <div class="form-group"><label>Purpose</label><input type="text" name="purpose" placeholder="e.g. Tuition Fee" required></div>
            </div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="4" placeholder="Explain what the loan is for and your repayment plan..." required></textarea></div>
            <div class="form-row">
              <div class="form-group"><label>Repayment Period</label><input type="text" name="repay_period_text" placeholder="e.g. Repay in 2-3 months" required></div>
              <div class="form-group" style="display:flex;align-items:center;padding-top:26px;">
                <label style="display:flex;align-items:center;gap:8px;font-weight:500;"><input type="checkbox" name="emi_ok" style="width:auto;"> Open to EMI</label>
              </div>
            </div>
            <button class="btn btn-primary" type="submit">Post Loan Request</button>
          </form>
        </div>
        <?php endif; ?>

        <div class="filter-tabs">
          <a href="loan_posts.php" class="active">All Loan Posts</a>
          <a href="loan_posts.php?new=1">+ New Request</a>
        </div>

        <?php if ($q): ?><p style="color:var(--text-muted);font-size:13px;">Search results for "<?= e($q) ?>" (<?= count($posts) ?>)</p><?php endif; ?>

        <?php if (!$posts): ?>
          <div class="card empty-state"><i class="fa-regular fa-folder-open"></i><p>No loan posts found.</p></div>
        <?php endif; ?>

        <?php foreach ($posts as $p): ?>
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
              <?php if ($p['status']==='funded'): ?><span class="tag" style="background:#f0fdf4;color:#15803d;">Funded</span><?php endif; ?>
            </div>
            <div class="post-actions">
              <a href="loan_details.php?id=<?= $p['id'] ?>" class="btn btn-primary"><i class="fa-solid fa-hand-holding-dollar"></i> View & Offer</a>
              <a href="messages.php?to=<?= $p['user_id'] ?>&post=<?= $p['id'] ?>" class="btn btn-outline"><i class="fa-regular fa-comment"></i> Message</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="content-side">
        <div class="safety-box">
          <i class="fa-solid fa-shield-halved"></i>
          <h4>Protected Transaction</h4>
          <p>Funds are only released after both parties confirm and digitally sign the agreement. All chats and offers are recorded for dispute resolution.</p>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
