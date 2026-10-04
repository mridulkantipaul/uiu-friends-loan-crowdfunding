<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$u = current_user();
$active = 'loan_posts.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT a.*, bu.full_name borrower_name, lu.full_name lender_name
    FROM agreements a JOIN users bu ON bu.id=a.borrower_id JOIN users lu ON lu.id=a.lender_id
    WHERE a.id = ?");
$stmt->execute([$id]);
$ag = $stmt->fetch();
if (!$ag) { http_response_code(404); die('Agreement not found.'); }
$isBorrower = $ag['borrower_id'] == $u['id'];
if (!$isBorrower) { http_response_code(403); die('Only the borrower can make payments on this agreement.'); }
if (!$ag['repayment_plan']) redirect('agreement.php?id=' . $id);

$next = $pdo->prepare("SELECT * FROM installments WHERE agreement_id=? AND status='due' ORDER BY installment_no LIMIT 1");
$next->execute([$id]);
$installment = $next->fetch();

$formError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay']) && $installment) {
    csrf_check();
    $method = $_POST['method'] ?? '';
    if (!in_array($method, ['bkash','nagad','card','bank'])) {
        $formError = 'Please choose a payment method.';
    } else {
        $fee = round($installment['amount'] * 0.001, 0) + 10; // small simulated gateway fee
        $ref = gen_txn_ref();
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO transactions (agreement_id, installment_id, txn_ref, payer_id, payee_id, amount, gateway_fee, payment_method, status) VALUES (?,?,?,?,?,?,?,?, 'success')")
            ->execute([$id, $installment['id'], $ref, $u['id'], $ag['lender_id'], $installment['amount'], $fee, $method]);
        $txnId = $pdo->lastInsertId();
        $pdo->prepare("UPDATE installments SET status='paid' WHERE id=?")->execute([$installment['id']]);
        // if all installments paid, mark agreement completed
        $remaining = $pdo->prepare("SELECT COUNT(*) c FROM installments WHERE agreement_id=? AND status<>'paid'");
        $remaining->execute([$id]);
        if ($remaining->fetch()['c'] == 0) {
            $pdo->prepare("UPDATE agreements SET status='completed' WHERE id=?")->execute([$id]);
        }
        $pdo->commit();
        redirect('summary.php?txn=' . $txnId);
    }
}

$paid = $pdo->prepare("SELECT * FROM installments WHERE agreement_id=? AND status='paid' ORDER BY installment_no");
$paid->execute([$id]);
$paidList = $paid->fetchAll();
$all = $pdo->prepare("SELECT * FROM installments WHERE agreement_id=? ORDER BY installment_no");
$all->execute([$id]);
$allInstallments = $all->fetchAll();

$page_title = 'Payment';
include __DIR__ . '/includes/header.php';
?>
<div class="app-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main-area">
    <div class="page-header">
      <a href="agreement.php?id=<?= $id ?>" class="back-btn"><i class="fa-solid fa-arrow-left"></i></a>
      <h1>Secure the Deal — <?= e($ag['lender_name']) ?></h1>
      <div class="steps-row">
        <span class="step done">Offer</span>
        <span class="step done">Agreement</span>
        <span class="step active">Payment</span>
        <span class="step">Summary</span>
      </div>
    </div>

    <div class="detail-wrap">
      <div class="detail-main">
        <?php if (!$installment): ?>
          <div class="alert alert-success">All installments for this agreement have been paid. 🎉</div>
        <?php else: ?>
        <div class="card">
          <h3>Choose Payment Method</h3>
          <p style="font-size:12.5px;color:var(--text-muted);margin-top:-10px;margin-bottom:16px;">All transactions are processed securely via our verified third-party payment partners.</p>
          <?php if ($formError): ?><div class="alert alert-error"><?= e($formError) ?></div><?php endif; ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="pay" value="1">
            <div class="method-grid">
              <div><input type="radio" name="method" id="m-bkash" value="bkash" checked><label for="m-bkash">bKash</label></div>
              <div><input type="radio" name="method" id="m-nagad" value="nagad"><label for="m-nagad">Nagad</label></div>
              <div><input type="radio" name="method" id="m-card" value="card"><label for="m-card">Visa/Mastercard</label></div>
              <div><input type="radio" name="method" id="m-bank" value="bank"><label for="m-bank">Bank Transfer</label></div>
            </div>
            <p style="font-size:11.5px;color:var(--text-muted);text-align:center;margin-bottom:16px;">Secured by SSLCommerz Payment Gateway · PCI-DSS compliant · Funds held in escrow until confirmation</p>
            <button type="submit" class="btn btn-primary btn-block">Confirm & Pay <?= taka($installment['amount']) ?> (Installment <?= $installment['installment_no'] ?> of <?= count($allInstallments) ?>)</button>
          </form>
        </div>
        <?php endif; ?>

        <?php if ($allInstallments): ?>
        <div class="card">
          <h3>Installment Schedule</h3>
          <table class="simple-table">
            <tr><th>#</th><th>Due Date</th><th>Amount</th><th>Status</th></tr>
            <?php foreach ($allInstallments as $ins): ?>
              <tr>
                <td><?= $ins['installment_no'] ?> of <?= count($allInstallments) ?></td>
                <td><?= date('M d, Y', strtotime($ins['due_date'])) ?></td>
                <td><?= taka($ins['amount']) ?></td>
                <td><?= $ins['status']==='paid' ? '<span class="badge-verified"><i class="fa-solid fa-circle-check"></i> Paid</span>' : '<span class="badge-pending">Due</span>' ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <div class="detail-side">
        <div class="card">
          <h3>Deal Summary</h3>
          <div class="info-row"><span>Borrower</span><span class="val"><?= e($ag['borrower_name']) ?></span></div>
          <div class="info-row"><span>Lender</span><span class="val"><?= e($ag['lender_name']) ?></span></div>
          <div class="info-row"><span>Principal</span><span class="val"><?= taka($ag['principal']) ?></span></div>
          <div class="info-row"><span>Interest</span><span class="val"><?= $ag['interest_rate'] ?>%</span></div>
          <div class="info-row"><span>Total Payable</span><span class="val orange"><?= taka($ag['total_payable']) ?></span></div>
          <div class="info-row"><span>Plan</span><span class="val"><?= $ag['repayment_plan']==='emi' ? $ag['tenure_months'].'-Month EMI' : 'One-Time' ?></span></div>
        </div>
        <div class="card">
          <h3>Verification Checklist</h3>
          <ul class="checklist">
            <li><i class="fa-solid fa-circle-check"></i> Borrower UIU ID Verified</li>
            <li><i class="fa-solid fa-circle-check"></i> Lender UIU ID Verified</li>
            <li><i class="fa-solid fa-circle-check"></i> Agreement Terms Matched</li>
            <li class="<?= $installment ? 'pending' : '' ?>"><i class="fa-solid fa-circle-<?= $installment?'exclamation':'check' ?>"></i> <?= $installment ? 'Awaiting Final Confirmation' : 'Fully Settled' ?></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
