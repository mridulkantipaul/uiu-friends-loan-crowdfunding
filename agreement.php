<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$u = current_user();
$active = 'loan_posts.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT a.*, lp.purpose,
    bu.full_name borrower_name, bu.avatar borrower_avatar, bu.uiu_id_verified borrower_verified,
    bu.nid_number borrower_nid, bu.nid_status borrower_nid_status, bu.address borrower_address,
    lu.full_name lender_name, lu.avatar lender_avatar, lu.uiu_id_verified lender_verified,
    lu.nid_number lender_nid, lu.nid_status lender_nid_status
    FROM agreements a
    JOIN loan_posts lp ON lp.id = a.loan_post_id
    JOIN users bu ON bu.id = a.borrower_id
    JOIN users lu ON lu.id = a.lender_id
    WHERE a.id = ?");
$stmt->execute([$id]);
$ag = $stmt->fetch();
if (!$ag) { http_response_code(404); die('Agreement not found.'); }

$isBorrower = $ag['borrower_id'] == $u['id'];
$isLender   = $ag['lender_id'] == $u['id'];
if (!$isBorrower && !$isLender) { http_response_code(403); die('You do not have access to this agreement.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sign'])) {
    csrf_check();
    if ($isBorrower) $pdo->prepare("UPDATE agreements SET borrower_signed=1 WHERE id=?")->execute([$id]);
    if ($isLender)   $pdo->prepare("UPDATE agreements SET lender_signed=1 WHERE id=?")->execute([$id]);
    // refresh
    $stmt->execute([$id]);
    $ag = $stmt->fetch();
    if ($ag['borrower_signed'] && $ag['lender_signed']) {
        $pdo->prepare("UPDATE agreements SET status='active' WHERE id=?")->execute([$id]);
        $ag['status'] = 'active';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['choose_plan']) && $isBorrower) {
    csrf_check();
    $plan = $_POST['plan'] === 'full' ? 'full' : 'emi';
    $pdo->prepare("UPDATE agreements SET repayment_plan=? WHERE id=?")->execute([$plan, $id]);

    // generate installment schedule if not present
    $chk = $pdo->prepare("SELECT COUNT(*) c FROM installments WHERE agreement_id=?");
    $chk->execute([$id]);
    if ($chk->fetch()['c'] == 0) {
        if ($plan === 'emi') {
            $n = max(1, $ag['tenure_months']);
            $each = round($ag['total_payable'] / $n, 2);
            for ($i = 1; $i <= $n; $i++) {
                $due = date('Y-m-d', strtotime("+$i month"));
                $pdo->prepare("INSERT INTO installments (agreement_id, installment_no, due_date, amount) VALUES (?,?,?,?)")
                    ->execute([$id, $i, $due, $each]);
            }
        } else {
            $due = date('Y-m-d', strtotime('+' . $ag['tenure_months'] . ' month'));
            $pdo->prepare("INSERT INTO installments (agreement_id, installment_no, due_date, amount) VALUES (?,1,?,?)")
                ->execute([$id, $due, $ag['total_payable']]);
        }
    }
    redirect('payment.php?id=' . $id);
}

$installments = $pdo->prepare("SELECT * FROM installments WHERE agreement_id=? ORDER BY installment_no");
$installments->execute([$id]);
$installments = $installments->fetchAll();

$page_title = 'Secure the Deal';
include __DIR__ . '/includes/header.php';
?>
<div class="app-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main-area">
    <div class="page-header">
      <a href="loan_details.php?id=<?= $ag['loan_post_id'] ?>" class="back-btn"><i class="fa-solid fa-arrow-left"></i></a>
      <h1>Secure the Deal — <?= e($isBorrower ? $ag['lender_name'] : $ag['borrower_name']) ?></h1>
      <div class="steps-row">
        <span class="step done">Offer</span>
        <span class="step active">Agreement</span>
        <span class="step">Payment</span>
        <span class="step">Summary</span>
      </div>
    </div>

    <div class="detail-wrap">
      <div class="detail-main">
        <div class="safety-box">
          <i class="fa-solid fa-shield-halved"></i>
          <h4>Anti-Fraud Escrow</h4>
          <p>Payments are held securely and only released per the signed agreement schedule. Both UIU IDs are verified, and this deal is logged immutably for dispute protection.</p>
        </div>

        <div class="card">
          <h3>Loan Agreement Policy</h3>
          <ol style="font-size:13.5px;line-height:1.9;color:#333;padding-left:18px;">
            <li><strong>Parties:</strong> This agreement is between <?= e($ag['lender_name']) ?> (Lender) and <?= e($ag['borrower_name']) ?> (Borrower), both verified UIU students.</li>
            <li><strong>Terms:</strong> Principal <?= taka($ag['principal']) ?> at <?= $ag['interest_rate'] ?>% interest, repayable over <?= $ag['tenure_months'] ?> months via EMI or one-time full payment.</li>
            <li><strong>Repayment:</strong> Borrower agrees to repay per the selected schedule. Late payments may affect Trust Score.</li>
            <li><strong>Fraud Protection:</strong> Funds are held and released only via UIU Friends' verified payment partner. Both parties' UIU IDs are verified and linked to this agreement.</li>
            <li><strong>Dispute Resolution:</strong> Any dispute will be resolved using recorded chat logs, this signed agreement, and platform mediation.</li>
            <li><strong>NID Identification:</strong> The Borrower (NID <?= e(mask_nid($ag['borrower_nid'])) ?>) and the Lender (NID <?= e(mask_nid($ag['lender_nid'])) ?>) are identified by their verified National IDs. If the Borrower fails to repay, the recorded NID and address details may be used for recovery and legal action.</li>
            <li><strong>E-Signature:</strong> Digitally signing below constitutes a legally acknowledged agreement between both parties.</li>
          </ol>

          <div class="form-row" style="margin-top:18px;">
            <div class="form-group">
              <label>Borrower Signature</label>
              <div class="info-row" style="border:1px solid var(--border);border-radius:10px;padding:12px;">
                <span><?= e($ag['borrower_name']) ?></span>
                <span class="val <?= $ag['borrower_signed'] ? 'green' : '' ?>"><?= $ag['borrower_signed'] ? '✓ Signed' : 'Pending' ?></span>
              </div>
            </div>
            <div class="form-group">
              <label>Lender Signature</label>
              <div class="info-row" style="border:1px solid var(--border);border-radius:10px;padding:12px;">
                <span><?= e($ag['lender_name']) ?></span>
                <span class="val <?= $ag['lender_signed'] ? 'green' : '' ?>"><?= $ag['lender_signed'] ? '✓ Signed' : 'Pending' ?></span>
              </div>
            </div>
          </div>

          <?php if (($isBorrower && !$ag['borrower_signed']) || ($isLender && !$ag['lender_signed'])): ?>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="sign" value="1">
              <button type="submit" class="btn btn-dark btn-block"><i class="fa-solid fa-signature"></i> Sign as <?= e($u['full_name']) ?></button>
            </form>
          <?php elseif ($ag['status'] === 'pending_confirmation'): ?>
            <div class="alert alert-info">Waiting for the other party to sign.</div>
          <?php else: ?>
            <div class="alert alert-success">Both parties have signed. Agreement is active.</div>
          <?php endif; ?>
        </div>

        <?php if ($ag['status'] === 'active' && $isBorrower): ?>
        <div class="card">
          <h3>Select Repayment Plan (Borrower)</h3>
          <?php if ($ag['repayment_plan']): ?>
            <div class="alert alert-success">Plan selected: <?= $ag['repayment_plan'] === 'emi' ? 'EMI — Installments' : 'One-Time Full Payment' ?></div>
            <a href="payment.php?id=<?= $id ?>" class="btn btn-primary btn-block">Continue to Payment</a>
          <?php else: ?>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="choose_plan" value="1">
              <div class="toggle-group">
                <input type="radio" name="plan" id="plan-emi" value="emi" checked><label for="plan-emi">EMI — Installments<br><small style="font-weight:400;"><?= $ag['tenure_months'] ?> monthly payments of <?= taka(round($ag['total_payable']/$ag['tenure_months'],2)) ?></small></label>
                <input type="radio" name="plan" id="plan-full" value="full"><label for="plan-full">One-Time Full Payment<br><small style="font-weight:400;">Pay <?= taka($ag['total_payable']) ?> in full</small></label>
              </div>
              <button type="submit" class="btn btn-primary btn-block">Confirm & Continue</button>
            </form>
          <?php endif; ?>
        </div>
        <?php elseif ($ag['status'] === 'active' && $isLender): ?>
          <div class="card"><p style="font-size:13.5px;color:var(--text-muted);">Waiting for <?= e($ag['borrower_name']) ?> to select a repayment plan and make payment.</p></div>
        <?php endif; ?>
      </div>

      <div class="detail-side">
        <div class="card">
          <h3>Deal Summary</h3>
          <div class="info-row"><span>Loan Amount</span><span class="val"><?= taka($ag['principal']) ?></span></div>
          <div class="info-row"><span>Interest</span><span class="val"><?= $ag['interest_rate'] ?>%</span></div>
          <div class="info-row"><span>Tenure</span><span class="val"><?= $ag['tenure_months'] ?> months</span></div>
          <div class="info-row"><span>Total Payable</span><span class="val orange"><?= taka($ag['total_payable']) ?></span></div>
          <div class="info-row"><span>Status</span><span class="val <?= $ag['status']==='active'?'green':'' ?>"><?= ucwords(str_replace('_',' ',$ag['status'])) ?></span></div>
        </div>
        <div class="card">
          <h3>Identity (NID)</h3>
          <div class="info-row"><span>Borrower</span><span class="val <?= $ag['borrower_nid_status']==='verified'?'green':'' ?>"><?= e(mask_nid($ag['borrower_nid'])) ?> <?= $ag['borrower_nid_status']==='verified' ? '✓' : '' ?></span></div>
          <div class="info-row"><span>Lender</span><span class="val <?= $ag['lender_nid_status']==='verified'?'green':'' ?>"><?= e(mask_nid($ag['lender_nid'])) ?> <?= $ag['lender_nid_status']==='verified' ? '✓' : '' ?></span></div>
          <a href="report.php?type=agreement&id=<?= $id ?>&user=<?= $isBorrower ? $ag['lender_id'] : $ag['borrower_id'] ?>&category=<?= $isBorrower ? 'unfair_lender' : 'not_repaying' ?>" class="btn btn-outline btn-block btn-sm" style="margin-top:12px;color:#b91c1c;"><i class="fa-solid fa-flag"></i> Report a problem with this deal</a>
        </div>
        <?php if ($installments): ?>
        <div class="card">
          <h3>Installment Schedule</h3>
          <table class="simple-table">
            <tr><th>#</th><th>Due</th><th>Amount</th></tr>
            <?php foreach ($installments as $ins): ?>
              <tr><td><?= $ins['installment_no'] ?></td><td><?= date('M d, Y', strtotime($ins['due_date'])) ?></td><td><?= taka($ins['amount']) ?></td></tr>
            <?php endforeach; ?>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>
