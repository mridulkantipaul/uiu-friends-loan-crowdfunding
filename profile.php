<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$u = current_user();
$active = 'profile.php';

$viewId = (int)($_GET['id'] ?? $u['id']);
$isSelf = $viewId == $u['id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$viewId]);
$profile = $stmt->fetch();
if (!$profile) { http_response_code(404); die('User not found.'); }

if ($isSelf && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    csrf_check();
    $name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if ($name) {
        $pdo->prepare("UPDATE users SET full_name=?, phone=? WHERE id=?")->execute([$name, $phone, $u['id']]);
        flash('success', 'Profile updated.');
        redirect('profile.php');
    }
}

$loanCount = $pdo->prepare("SELECT COUNT(*) c FROM agreements WHERE (borrower_id=? OR lender_id=?) AND status='completed'");
$loanCount->execute([$viewId, $viewId]);
$completedLoans = $loanCount->fetch()['c'];

$campCount = $pdo->prepare("SELECT COUNT(*) c FROM campaigns WHERE organizer_id=?");
$campCount->execute([$viewId]);
$campaignsOrganized = $campCount->fetch()['c'];

$totalBorrowed = $pdo->prepare("SELECT COALESCE(SUM(amount),0) s FROM transactions t JOIN agreements a ON a.id=t.agreement_id WHERE a.borrower_id=?");
$totalBorrowed->execute([$viewId]); $totalBorrowed = $totalBorrowed->fetch()['s'];

$totalLent = $pdo->prepare("SELECT COALESCE(SUM(amount),0) s FROM transactions t JOIN agreements a ON a.id=t.agreement_id WHERE a.lender_id=?");
$totalLent->execute([$viewId]); $totalLent = $totalLent->fetch()['s'];

$disputeCount = $pdo->prepare("SELECT COUNT(*) c FROM agreements WHERE (borrower_id=? OR lender_id=?) AND status='disputed'");
$disputeCount->execute([$viewId, $viewId]); $disputeCount = $disputeCount->fetch()['c'];

$reviewStmt = $pdo->prepare("SELECT r.*, u.full_name reviewer_name FROM reviews r JOIN users u ON u.id=r.reviewer_id WHERE r.reviewee_id=? ORDER BY r.created_at DESC");
$reviewStmt->execute([$viewId]);
$reviews = $reviewStmt->fetchAll();

$histStmt = $pdo->prepare("
    (SELECT t.created_at, t.amount, 'Loan repayment' label, lu.full_name other_name, 'out' dir
     FROM transactions t JOIN agreements a ON a.id=t.agreement_id JOIN users lu ON lu.id=a.lender_id WHERE a.borrower_id=?)
    UNION ALL
    (SELECT t.created_at, t.amount, 'Loan received' label, bu.full_name other_name, 'in' dir
     FROM transactions t JOIN agreements a ON a.id=t.agreement_id JOIN users bu ON bu.id=a.borrower_id WHERE a.lender_id=?)
    UNION ALL
    (SELECT created_at, amount, 'Donated' label, (SELECT title FROM campaigns WHERE id=donations.campaign_id) other_name, 'out' dir FROM donations WHERE donor_id=?)
    ORDER BY created_at DESC LIMIT 6");
$histStmt->execute([$viewId, $viewId, $viewId]);
$history = $histStmt->fetchAll();

$breakdown = user_trust_breakdown($profile);

$page_title = $isSelf ? 'My Profile' : $profile['full_name'];
include __DIR__ . '/includes/header.php';
?>
<div class="app-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main-area">
    <div class="page-header">
      <a href="home.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i></a>
      <h1><?= $isSelf ? 'My Profile' : e($profile['full_name']) ?></h1>
      <?php if ($isSelf): ?>
      <div style="margin-left:auto;">
        <button class="btn btn-outline" onclick="document.getElementById('editForm').style.display='block'">Edit Profile</button>
      </div>
      <?php endif; ?>
    </div>

    <div class="detail-wrap">
      <div class="detail-main">
        <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

        <div class="profile-banner">
          <div class="left">
            <img src="<?= e($profile['avatar']) ?>" alt="">
            <div>
              <h2><?= e($profile['full_name']) ?> <?= $profile['uiu_id_verified'] ? '<span class="badge-verified"><i class="fa-solid fa-circle-check"></i> UIU ID Verified</span>' : '' ?></h2>
              <div class="meta"><?= e($profile['department']) ?> · Batch <?= e($profile['batch']) ?> · Student ID: <?= e(substr($profile['student_id'],0,7)) ?>xxx · Joined <?= date('M Y', strtotime($profile['created_at'])) ?></div>
              <div class="stats-row">
                <span><i class="fa-solid fa-star" style="color:var(--orange)"></i> <?= number_format($profile['trust_score'],1) ?> Trust Score</span>
                <span><i class="fa-solid fa-hand-holding-dollar"></i> <?= $completedLoans ?> Loans Completed</span>
                <span><i class="fa-regular fa-heart"></i> <?= $campaignsOrganized ?> Campaigns Organized</span>
              </div>
            </div>
          </div>
          <?php if (!$isSelf): ?>
          <div style="display:flex;flex-direction:column;gap:8px;">
            <a href="messages.php?to=<?= $profile['id'] ?>" class="btn btn-primary">Message</a>
            <a href="report.php?type=user&user=<?= $profile['id'] ?>" class="btn btn-outline btn-sm" style="color:#b91c1c;"><i class="fa-solid fa-flag"></i> Report user</a>
          </div>
          <?php endif; ?>
        </div>

        <?php if ($isSelf): ?>
        <div class="card" id="editForm" style="display:none;">
          <h3>Edit Profile</h3>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="update_profile" value="1">
            <div class="form-group"><label>Full Name</label><input type="text" name="full_name" value="<?= e($profile['full_name']) ?>"></div>
            <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?= e($profile['phone']) ?>"></div>
            <button class="btn btn-primary" type="submit">Save Changes</button>
          </form>
        </div>
        <?php endif; ?>

        <div class="stat-cards">
          <div class="stat-card"><div class="num"><?= taka($totalBorrowed) ?></div><div class="lbl">Total Borrowed</div></div>
          <div class="stat-card"><div class="num"><?= taka($totalLent) ?></div><div class="lbl">Total Lent</div></div>
          <div class="stat-card"><div class="num" style="color:<?= $disputeCount>0?'#b91c1c':'inherit' ?>"><?= $disputeCount ?></div><div class="lbl">Disputes</div></div>
          <div class="stat-card"><div class="num"><?= $breakdown['review_count'] ?></div><div class="lbl">Reviews</div></div>
        </div>

        <div class="card">
          <h3>Transaction History</h3>
          <?php if (!$history): ?><p style="color:var(--text-muted);font-size:13.5px;">No transactions yet.</p><?php endif; ?>
          <?php foreach ($history as $h): ?>
            <div class="tx-item">
              <div class="tx-icon"><i class="fa-solid fa-<?= $h['dir']==='in'?'arrow-down':'arrow-up' ?>"></i></div>
              <div><div class="title"><?= e($h['label']) ?><?= $h['other_name'] ? ' — ' . e($h['other_name']) : '' ?></div><div class="sub"><?= date('M d, Y', strtotime($h['created_at'])) ?></div></div>
              <div class="amt" style="color:<?= $h['dir']==='in'?'var(--green)':'inherit' ?>;"><?= $h['dir']==='in'?'+':'-' ?><?= taka($h['amount']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="card">
          <h3>Reviews & Ratings (<?= count($reviews) ?>)</h3>
          <?php foreach (array_slice($reviews,0,6) as $r): ?>
            <div class="review-item">
              <div class="who"><span><?= e($r['reviewer_name']) ?></span><span style="font-weight:400;color:var(--text-muted);font-size:12px;"><?= date('M d, Y', strtotime($r['created_at'])) ?></span></div>
              <div class="stars"><?= str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']) ?></div>
              <?php if ($r['comment']): ?><div class="comment">"<?= e($r['comment']) ?>"</div><?php endif; ?>
            </div>
          <?php endforeach; ?>
          <?php if (!$reviews): ?><p style="color:var(--text-muted);font-size:13.5px;">No reviews yet.</p><?php endif; ?>
        </div>
      </div>

      <div class="detail-side">
        <div class="card">
          <h3>Verification Status</h3>
          <div class="info-row"><span><i class="fa-solid fa-address-card"></i> National ID (NID)</span><span class="val <?= $profile['nid_status']==='verified'?'green':'' ?>"><?= e(nid_status_label($profile['nid_status'])) ?></span></div>
          <div class="info-row"><span><i class="fa-solid fa-id-card"></i> UIU Student ID</span><span class="val <?= $profile['uiu_id_verified']?'green':'' ?>"><?= $profile['uiu_id_verified'] ? 'Verified' : 'Pending' ?></span></div>
          <div class="info-row"><span><i class="fa-solid fa-envelope"></i> University Email</span><span class="val <?= $profile['email_verified']?'green':'' ?>"><?= $profile['email_verified'] ? 'Verified' : 'Pending' ?></span></div>
          <div class="info-row"><span><i class="fa-solid fa-phone"></i> Phone Number</span><span class="val <?= $profile['phone_verified']?'green':'' ?>"><?= $profile['phone_verified'] ? 'Verified' : 'Pending' ?></span></div>
          <div class="info-row"><span><i class="fa-solid fa-building-columns"></i> Payment Account</span><span class="val <?= $profile['payment_linked']?'green':'' ?>"><?= $profile['payment_linked'] ? 'Linked' : 'Not Linked' ?></span></div>
        </div>
        <div class="safety-box">
          <i class="fa-solid fa-shield-halved"></i>
          <h4>Agreement Policy Accepted</h4>
          <p>This member has agreed to UIU Friends' Lender & Borrower Agreement Policy for all transactions.</p>
        </div>
        <div class="card">
          <h3>Trust Score Breakdown</h3>
          <div class="info-row"><span>Repayment Reliability</span><span class="val orange"><?= $breakdown['repayment_reliability'] ?>%</span></div>
          <div class="info-row"><span>Response Time</span><span class="val">Excellent</span></div>
          <div class="info-row"><span>Community Reviews</span><span class="val orange"><?= $breakdown['community_reviews'] ?> / 5</span></div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
