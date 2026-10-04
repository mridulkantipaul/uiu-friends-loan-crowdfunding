<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$u = current_user();
$active = 'loan_posts.php';

$txnId = (int)($_GET['txn'] ?? 0);
$stmt = $pdo->prepare("SELECT t.*, i.installment_no, i.due_date,
    (SELECT COUNT(*) FROM installments WHERE agreement_id=t.agreement_id) total_installments,
    a.principal, a.interest_rate, a.tenure_months,
    pu.full_name payer_name, py.full_name payee_name, py.id payee_id
    FROM transactions t
    LEFT JOIN installments i ON i.id = t.installment_id
    JOIN agreements a ON a.id = t.agreement_id
    JOIN users pu ON pu.id = t.payer_id
    JOIN users py ON py.id = t.payee_id
    WHERE t.id = ?");
$stmt->execute([$txnId]);
$txn = $stmt->fetch();
if (!$txn || ($txn['payer_id'] != $u['id'] && $txn['payee_id'] != $u['id'] && $u['role'] !== 'admin')) {
    if (!$txn) { http_response_code(404); die('Transaction not found.'); }
}

$remaining = $pdo->prepare("SELECT * FROM installments WHERE agreement_id=? AND status='due' ORDER BY installment_no");
$remaining->execute([$txn['agreement_id']]);
$remainingList = $remaining->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    csrf_check();
    $rating = max(1, min(5, (int)$_POST['rating']));
    $comment = trim($_POST['comment'] ?? '');
    $pdo->prepare("INSERT INTO reviews (reviewee_id, reviewer_id, agreement_id, rating, comment) VALUES (?,?,?,?,?)")
        ->execute([$txn['payee_id'], $u['id'], $txn['agreement_id'], $rating, $comment]);
    flash('success', 'Thanks for your review!');
    redirect('summary.php?txn=' . $txnId);
}

$page_title = 'Transaction Summary';
include __DIR__ . '/includes/header.php';
?>
<div class="app-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main-area">
    <div class="page-header">
      <a href="wallet.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i></a>
      <h1>Transaction Summary</h1>
      <div class="steps-row">
        <span class="step done">Offer</span>
        <span class="step done">Agreement</span>
        <span class="step done">Payment</span>
        <span class="step active">Summary</span>
      </div>
    </div>

    <div class="detail-wrap">
      <div class="detail-main">
        <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

        <div class="card" style="text-align:center;padding:34px 20px;">
          <i class="fa-solid fa-circle-check" style="font-size:44px;color:#16a34a;margin-bottom:14px;"></i>
          <h3 style="margin:0 0 6px;">Payment Successful</h3>
          <p style="color:var(--text-muted);font-size:13.5px;">Your installment has been securely processed via <?= ucfirst($txn['payment_method']) ?>.</p>
        </div>

        <div class="card">
          <div class="alert alert-info" style="display:flex;align-items:center;gap:10px;"><i class="fa-solid fa-lock"></i> This payment is logged against your signed agreement and cannot be altered. Both parties receive this same summary.</div>
          <div class="info-row"><span>Transaction ID</span><span class="val">#<?= e($txn['txn_ref']) ?> <span class="badge-verified" style="margin-left:6px;">Verified Payment</span></span></div>
          <div class="info-row"><span>Date & Time</span><span class="val"><?= date('F d, Y — h:i A', strtotime($txn['created_at'])) ?></span></div>
          <div class="info-row"><span>Payment Method</span><span class="val"><?= ucfirst($txn['payment_method']) ?> (Third-Party Gateway)</span></div>
          <div class="info-row"><span>Lender</span><span class="val"><?= e($txn['payee_name']) ?></span></div>
          <div class="info-row"><span>Borrower</span><span class="val"><?= e($txn['payer_name']) ?></span></div>
          <div class="info-row"><span>Principal Amount</span><span class="val"><?= taka($txn['principal']) ?></span></div>
          <div class="info-row"><span>Interest (<?= $txn['interest_rate'] ?>%)</span><span class="val"><?= taka($txn['principal'] * $txn['interest_rate'] / 100) ?></span></div>
          <div class="info-row"><span>Gateway Fee</span><span class="val"><?= taka($txn['gateway_fee']) ?></span></div>
          <?php if ($txn['installment_no']): ?>
          <div class="info-row"><span>Installment Paid</span><span class="val"><?= $txn['installment_no'] ?> of <?= $txn['total_installments'] ?></span></div>
          <?php endif; ?>
          <div class="info-row"><span>Total Paid Today</span><span class="val orange"><?= taka($txn['amount'] + $txn['gateway_fee']) ?></span></div>
        </div>

        <?php if ($remainingList): ?>
        <div class="card">
          <h3>Remaining Schedule</h3>
          <table class="simple-table">
            <tr><th>Installment</th><th>Due</th><th>Amount</th></tr>
            <?php foreach ($remainingList as $r): ?>
              <tr><td>Installment <?= $r['installment_no'] ?></td><td><?= date('M d, Y', strtotime($r['due_date'])) ?></td><td><?= taka($r['amount']) ?></td></tr>
            <?php endforeach; ?>
          </table>
        </div>
        <?php endif; ?>

        <div class="post-actions" style="border:none;padding:0;">
          <a href="#" onclick="window.print();return false;" class="btn btn-outline"><i class="fa-solid fa-download"></i> Download Receipt (PDF)</a>
          <a href="wallet.php" class="btn btn-primary">Go to Wallet Dashboard</a>
        </div>

        <?php if ($u['id'] == $txn['payer_id']): ?>
        <div class="card">
          <h3>Rate this Lender</h3>
          <p style="font-size:13px;color:var(--text-muted);margin-top:-8px;">How was your experience with <?= e($txn['payee_name']) ?>?</p>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="submit_review" value="1">
            <div class="form-group">
              <label>Rating</label>
              <select name="rating">
                <option value="5">★★★★★ Excellent</option>
                <option value="4">★★★★ Good</option>
                <option value="3">★★★ Average</option>
                <option value="2">★★ Poor</option>
                <option value="1">★ Very Poor</option>
              </select>
            </div>
            <div class="form-group"><textarea name="comment" rows="3" placeholder="Leave a short review..."></textarea></div>
            <button type="submit" class="btn btn-primary">Submit Review</button>
          </form>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>
