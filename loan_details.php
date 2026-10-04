<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$u = current_user();
$active = 'loan_posts.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT lp.*, u.full_name, u.department, u.batch, u.trust_score, u.uiu_id_verified, u.nid_status, u.nid_number, u.avatar, u.id as owner_id
    FROM loan_posts lp JOIN users u ON u.id = lp.user_id WHERE lp.id = ?");
$stmt->execute([$id]);
$post = $stmt->fetch();
if (!$post) { http_response_code(404); die('Loan post not found.'); }

$isOwner = $post['owner_id'] == $u['id'];
$formError = null;

/* Make an offer (lender) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_offer']) && !$isOwner) {
    csrf_check();
    $amount = (float)($_POST['offer_amount'] ?? 0);
    $rate   = (float)($_POST['interest_rate'] ?? 0);
    $tenure = (int)($_POST['tenure_months'] ?? 0);
    if ($gate = nid_gate($u, 'make an offer')) {
        $formError = $gate;
    } elseif ($amount > 0 && $tenure > 0 && !empty($_POST['agree_policy'])) {
        $stmt = $pdo->prepare("INSERT INTO loan_offers (loan_post_id, lender_id, amount, interest_rate, tenure_months) VALUES (?,?,?,?,?)");
        $stmt->execute([$id, $u['id'], $amount, $rate, $tenure]);
        flash('success', 'Your offer has been sent to ' . $post['full_name'] . '.');
        redirect('loan_details.php?id=' . $id);
    } else {
        $formError = 'Please complete the offer form and agree to the Lender Agreement Policy.';
    }
}

/* Accept an offer (borrower only) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_offer']) && $isOwner) {
    csrf_check();
    $offerId = (int)$_POST['offer_id'];
    if (!nid_verified($u)) { redirect('security.php#nid'); }
    $stmt = $pdo->prepare("SELECT * FROM loan_offers WHERE id=? AND loan_post_id=? AND status='pending'");
    $stmt->execute([$offerId, $id]);
    $offer = $stmt->fetch();
    if ($offer) {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE loan_offers SET status='accepted' WHERE id=?")->execute([$offerId]);
        $pdo->prepare("UPDATE loan_offers SET status='rejected' WHERE loan_post_id=? AND id<>? AND status='pending'")->execute([$id, $offerId]);
        $pdo->prepare("UPDATE loan_posts SET status='funded' WHERE id=?")->execute([$id]);
        $total = round($offer['amount'] * (1 + $offer['interest_rate']/100), 2);
        $pdo->prepare("INSERT INTO agreements (loan_post_id, offer_id, borrower_id, lender_id, principal, interest_rate, tenure_months, total_payable, status)
                        VALUES (?,?,?,?,?,?,?,?,'pending_confirmation')")
            ->execute([$id, $offerId, $u['id'], $offer['lender_id'], $offer['amount'], $offer['interest_rate'], $offer['tenure_months'], $total]);
        $agreementId = $pdo->lastInsertId();
        $pdo->commit();
        redirect('agreement.php?id=' . $agreementId);
    }
}

$offStmt = $pdo->prepare("SELECT lo.*, u.full_name, u.avatar, u.trust_score FROM loan_offers lo JOIN users u ON u.id=lo.lender_id WHERE lo.loan_post_id=? ORDER BY lo.created_at DESC");
$offStmt->execute([$id]);
$offers = $offStmt->fetchAll();

// existing agreement for this post?
$agStmt = $pdo->prepare("SELECT id FROM agreements WHERE loan_post_id=? LIMIT 1");
$agStmt->execute([$id]);
$existingAgreement = $agStmt->fetch();

$page_title = 'Loan Request Details';
include __DIR__ . '/includes/header.php';
?>
<div class="app-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main-area">
    <div class="page-header">
      <a href="loan_posts.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i></a>
      <h1>Loan Request Details</h1>
    </div>

    <div class="detail-wrap">
      <div class="detail-main">

        <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

        <div class="card">
          <div class="post-head">
            <div class="post-user">
              <img src="<?= e($post['avatar']) ?>" alt="">
              <div>
                <div class="name" style="font-size:16px;"><?= e($post['full_name']) ?> <?= $post['uiu_id_verified'] ? '<span class="badge-verified"><i class="fa-solid fa-circle-check"></i> Verified</span>' : '' ?></div>
                <div class="meta"><?= e($post['department']) ?> · Batch <?= e($post['batch']) ?> · <i class="fa-solid fa-star" style="color:#f97316"></i> <?= number_format($post['trust_score'],1) ?></div>
              </div>
            </div>
            <div class="post-time">Posted <?= time_ago($post['created_at']) ?></div>
          </div>
          <div class="post-amount"><?= taka($post['amount']) ?> <span>requested</span></div>
          <div class="post-desc"><?= nl2br(e($post['description'])) ?></div>
          <div class="tag-row">
            <?php if ($post['emi_ok']): ?><span class="tag">EMI Preferred</span><?php endif; ?>
            <span class="tag"><?= e($post['repay_period_text']) ?></span>
            <span class="tag">Purpose: <?= e($post['purpose']) ?></span>
          </div>
          <?php if (!$isOwner): ?>
          <div class="post-actions">
            <a href="#offer-form" class="btn btn-primary"><i class="fa-solid fa-hand-holding-dollar"></i> Offer</a>
            <a href="messages.php?to=<?= $post['owner_id'] ?>&post=<?= $id ?>" class="btn btn-outline"><i class="fa-regular fa-comment"></i> Message <?= e(explode(' ',$post['full_name'])[0]) ?></a>
          </div>
          <?php endif; ?>
        </div>

        <?php if (!$isOwner && $post['status'] === 'open' && ($gate = nid_gate($u, 'make an offer'))): ?>
        <div class="card" id="offer-form">
          <h3><i class="fa-solid fa-address-card"></i> NID verification required to lend</h3>
          <div class="alert alert-error"><?= e($gate) ?></div>
          <a href="security.php#nid" class="btn btn-primary"><i class="fa-solid fa-address-card"></i> Verify my NID</a>
        </div>
        <?php elseif (!$isOwner && $post['status'] === 'open'): ?>
        <div class="card" id="offer-form" style="background:#fff7ed;border-color:#fed7aa;">
          <h3>Make an Offer as Lender</h3>
          <p style="font-size:13px;color:var(--text-muted);margin-top:-8px;margin-bottom:16px;">Choose to lend based on interest, a fixed time period, or both.</p>
          <?php if ($formError): ?><div class="alert alert-error"><?= e($formError) ?></div><?php endif; ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="make_offer" value="1">
            <div class="form-row">
              <div class="form-group"><label>Amount to Lend (৳)</label><input type="number" name="offer_amount" value="<?= (int)$post['amount'] ?>" min="1" required></div>
              <div class="form-group"><label>Interest Rate (%)</label><input type="number" name="interest_rate" value="0" min="0" step="0.5"></div>
              <div class="form-group"><label>Repayment Period (months)</label><input type="number" name="tenure_months" value="3" min="1" required></div>
            </div>
            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;font-weight:500;">
                <input type="checkbox" name="agree_policy" style="width:auto;" required>
                I agree to the <a href="#" style="color:var(--orange);">Lender Agreement Policy</a> and understand this offer is binding once confirmed by the borrower.
              </label>
            </div>
            <button type="submit" class="btn btn-dark"><i class="fa-solid fa-check"></i> Confirm Offer</button>
          </form>
        </div>
        <?php endif; ?>

        <div class="card">
          <h3>Offers from Lenders (<?= count($offers) ?>)</h3>
          <?php if (!$offers): ?>
            <p style="color:var(--text-muted);font-size:13.5px;">No offers yet. Be the first to offer!</p>
          <?php endif; ?>
          <?php foreach ($offers as $o): ?>
            <div class="offer-item">
              <img src="<?= e($o['avatar']) ?>" alt="">
              <div>
                <div class="name"><?= e($o['full_name']) ?> <i class="fa-solid fa-star" style="color:#f97316;font-size:11px;"></i> <?= number_format($o['trust_score'],1) ?></div>
                <div class="sub"><?= taka($o['amount']) ?> · <?= $o['interest_rate'] > 0 ? $o['interest_rate'].'% interest' : 'No interest' ?> · <?= $o['tenure_months'] ?> months</div>
              </div>
              <div class="spacer"></div>
              <?php if ($o['status'] === 'accepted'): ?>
                <span class="tag" style="background:#f0fdf4;color:#15803d;">Accepted</span>
              <?php elseif ($o['status'] === 'rejected'): ?>
                <span class="tag" style="background:#fef2f2;color:#b91c1c;">Not selected</span>
              <?php elseif ($isOwner && $post['status'] === 'open'): ?>
                <form method="post">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="accept_offer" value="1">
                  <input type="hidden" name="offer_id" value="<?= $o['id'] ?>">
                  <button type="submit" class="btn btn-primary btn-sm">Accept</button>
                </form>
              <?php else: ?>
                <span class="tag">Pending</span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="detail-side">
        <div class="card">
          <h3>Borrower Trust Profile</h3>
          <div class="info-row"><span>Trust Score</span><span class="val orange"><?= number_format($post['trust_score'],1) ?> / 5</span></div>
          <div class="info-row"><span>NID Status</span><span class="val <?= $post['nid_status']==='verified'?'green':'' ?>"><?= $post['nid_status']==='verified' ? 'Verified' : e(nid_status_label($post['nid_status'])) ?></span></div>
          <div class="info-row"><span>NID Number</span><span class="val"><?= e(mask_nid($post['nid_number'])) ?></span></div>
          <div class="info-row"><span>UIU ID Status</span><span class="val <?= $post['uiu_id_verified']?'green':'' ?>"><?= $post['uiu_id_verified'] ? 'Verified' : 'Pending' ?></span></div>
          <div class="info-row"><span>Department</span><span class="val"><?= e($post['department']) ?></span></div>
          <div class="info-row"><span>Batch</span><span class="val"><?= e($post['batch']) ?></span></div>
        </div>
        <div class="safety-box">
          <i class="fa-solid fa-shield-halved"></i>
          <h4>Protected Transaction</h4>
          <p>Funds are only released after both parties confirm and digitally sign the agreement. All chats and offers are recorded for dispute resolution.</p>
        </div>
        <?php if (!$isOwner): ?>
        <a href="report.php?type=loan_post&id=<?= $id ?>&user=<?= $post['owner_id'] ?>" class="btn btn-outline btn-block btn-sm" style="color:#b91c1c;"><i class="fa-solid fa-flag"></i> Report this post</a>
        <?php endif; ?>
        <?php if ($existingAgreement): ?>
        <a href="agreement.php?id=<?= $existingAgreement['id'] ?>" class="btn btn-outline btn-block">View Agreement</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>
