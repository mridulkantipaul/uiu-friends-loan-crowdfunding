<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$u = current_user();
$active = 'wallet.php';

$borrowed = $pdo->prepare("SELECT COALESCE(SUM(t.amount),0) s FROM transactions t WHERE t.payer_id=?");
$borrowed->execute([$u['id']]); $totalBorrowed = $borrowed->fetch()['s'];

$lent = $pdo->prepare("SELECT COALESCE(SUM(t.amount),0) s FROM transactions t WHERE t.payee_id=?");
$lent->execute([$u['id']]); $totalLent = $lent->fetch()['s'];

$donated = $pdo->prepare("SELECT COALESCE(SUM(amount),0) s FROM donations WHERE donor_id=?");
$donated->execute([$u['id']]); $totalDonated = $donated->fetch()['s'];

// active agreements as borrower with due installments
$dueStmt = $pdo->prepare("SELECT a.id agreement_id, a.total_payable, lu.full_name lender_name, i.installment_no, i.due_date, i.amount
    FROM agreements a JOIN users lu ON lu.id=a.lender_id
    JOIN installments i ON i.agreement_id=a.id AND i.status='due'
    WHERE a.borrower_id=? AND a.status='active' ORDER BY i.due_date ASC");
$dueStmt->execute([$u['id']]);
$dueInstallments = $dueStmt->fetchAll();

// transaction history (both as payer and payee) + donations
$txStmt = $pdo->prepare("
    (SELECT t.id, t.amount, t.created_at, t.txn_ref, 'loan_paid' type, lu.full_name other_name
     FROM transactions t JOIN agreements a ON a.id=t.agreement_id JOIN users lu ON lu.id=a.lender_id
     WHERE t.payer_id=?)
    UNION ALL
    (SELECT t.id, t.amount, t.created_at, t.txn_ref, 'loan_received' type, bu.full_name other_name
     FROM transactions t JOIN agreements a ON a.id=t.agreement_id JOIN users bu ON bu.id=a.borrower_id
     WHERE t.payee_id=?)
    UNION ALL
    (SELECT d.id, d.amount, d.created_at, d.txn_ref, 'donation' type, c.title other_name
     FROM donations d JOIN campaigns c ON c.id=d.campaign_id WHERE d.donor_id=?)
    ORDER BY created_at DESC LIMIT 20");
$txStmt->execute([$u['id'], $u['id'], $u['id']]);
$history = $txStmt->fetchAll();

$page_title = 'My Wallet';
include __DIR__ . '/includes/header.php';
?>
<div class="app-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main-area">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <div class="content-wrap">
      <div class="content-main">
        <div class="stat-cards">
          <div class="stat-card"><div class="num"><?= taka($totalBorrowed) ?></div><div class="lbl">Total Repaid (as Borrower)</div></div>
          <div class="stat-card"><div class="num"><?= taka($totalLent) ?></div><div class="lbl">Total Received (as Lender)</div></div>
          <div class="stat-card"><div class="num"><?= taka($totalDonated) ?></div><div class="lbl">Total Donated</div></div>
          <div class="stat-card"><div class="num"><?= count($dueInstallments) ?></div><div class="lbl">Installments Due</div></div>
        </div>

        <?php if ($dueInstallments): ?>
        <div class="card">
          <h3><i class="fa-solid fa-clock"></i> Upcoming Payments</h3>
          <?php foreach ($dueInstallments as $d): ?>
            <div class="tx-item">
              <div class="tx-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
              <div>
                <div class="title">Installment <?= $d['installment_no'] ?> to <?= e($d['lender_name']) ?></div>
                <div class="sub">Due <?= date('M d, Y', strtotime($d['due_date'])) ?></div>
              </div>
              <div class="amt"><?= taka($d['amount']) ?></div>
              <a href="payment.php?id=<?= $d['agreement_id'] ?>" class="btn btn-primary btn-sm" style="margin-left:14px;">Pay Now</a>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="card">
          <h3>Transaction History</h3>
          <?php if (!$history): ?><p style="color:var(--text-muted);font-size:13.5px;">No transactions yet.</p><?php endif; ?>
          <?php foreach ($history as $h):
            $icon = $h['type']==='donation' ? 'fa-heart' : 'fa-hand-holding-dollar';
            $label = $h['type']==='loan_paid' ? 'Loan repayment to ' . $h['other_name']
                   : ($h['type']==='loan_received' ? 'Loan payment received from ' . $h['other_name']
                   : 'Donated to ' . $h['other_name']);
            $sign = $h['type']==='loan_received' ? '+' : '-';
          ?>
            <div class="tx-item">
              <div class="tx-icon"><i class="fa-solid <?= $icon ?>"></i></div>
              <div>
                <div class="title"><?= e($label) ?></div>
                <div class="sub"><?= date('M d, Y', strtotime($h['created_at'])) ?> · <?= e($h['txn_ref']) ?></div>
              </div>
              <div class="amt" style="color:<?= $sign==='+' ? 'var(--green)':'inherit' ?>;"><?= $sign ?><?= taka($h['amount']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="content-side">
        <div class="card">
          <h3>Payment Accounts</h3>
          <div class="info-row"><span><i class="fa-solid fa-mobile-screen"></i> bKash</span><span class="val <?= $u['payment_linked']?'green':'' ?>"><?= $u['payment_linked']?'Linked':'Not Linked' ?></span></div>
          <div class="info-row"><span><i class="fa-solid fa-mobile-screen"></i> Nagad</span><span class="val">Available</span></div>
          <div class="info-row"><span><i class="fa-solid fa-credit-card"></i> Card</span><span class="val">Available</span></div>
        </div>
        <div class="safety-box">
          <i class="fa-solid fa-shield-halved"></i>
          <h4>Escrow Protected</h4>
          <p>All wallet activity is tied to signed agreements and verified transactions. Funds are never released outside the platform's payment partners.</p>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
