<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$admin = current_admin();
$active = 'transactions.php';

$loanTx = $pdo->query("SELECT t.*, u1.full_name payer, u2.full_name payee FROM transactions t
    JOIN users u1 ON u1.id=t.payer_id JOIN users u2 ON u2.id=t.payee_id ORDER BY t.created_at DESC LIMIT 100")->fetchAll();
$donationTx = $pdo->query("SELECT d.*, u.full_name donor, c.title campaign_title FROM donations d
    JOIN users u ON u.id=d.donor_id JOIN campaigns c ON c.id=d.campaign_id ORDER BY d.created_at DESC LIMIT 100")->fetchAll();

$totalLoanVolume = $pdo->query("SELECT COALESCE(SUM(amount),0) s FROM transactions")->fetch()['s'];
$totalFees = $pdo->query("SELECT COALESCE(SUM(gateway_fee),0) s FROM transactions")->fetch()['s'];
$totalDonationVolume = $pdo->query("SELECT COALESCE(SUM(amount),0) s FROM donations")->fetch()['s'];

$page_title = 'Transactions';
$base = '../';
$use_admin_css = true;
include __DIR__ . '/../includes/header.php';
?>
<div class="admin-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar"><h1>Transactions</h1><div class="mini-user"><img src="<?= e($admin['avatar']) ?>"><span><?= e($admin['full_name']) ?></span></div></div>
    <div class="admin-content">

      <div class="kpi-row" style="grid-template-columns:repeat(3,1fr);">
        <div class="kpi-card">
          <div class="kpi-head"><div class="icon" style="background:#fff3e8;color:#f97316;"><i class="fa-solid fa-sack-dollar"></i></div><div class="kpi-title">Loan Payment Volume</div></div>
          <div class="num"><?= taka($totalLoanVolume) ?></div><div class="lbl">total repayments processed</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-head"><div class="icon" style="background:#fdf2f8;color:#db2777;"><i class="fa-solid fa-heart"></i></div><div class="kpi-title">Donation Volume</div></div>
          <div class="num"><?= taka($totalDonationVolume) ?></div><div class="lbl">raised through campaigns</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-head"><div class="icon" style="background:#eff6ff;color:#2563eb;"><i class="fa-solid fa-coins"></i></div><div class="kpi-title">Gateway Fees</div></div>
          <div class="num"><?= taka($totalFees) ?></div><div class="lbl">collected from payments</div>
        </div>
      </div>

      <div class="admin-table-card" style="margin-bottom:22px;">
        <div class="head"><h3>Loan Payments</h3></div>
        <table class="admin-table">
          <tr><th>Txn Ref</th><th>Payer</th><th>Payee</th><th>Amount</th><th>Fee</th><th>Method</th><th>Status</th><th>Date</th></tr>
          <?php foreach ($loanTx as $t): ?>
          <tr>
            <td><?= e($t['txn_ref']) ?></td>
            <td><?= e($t['payer']) ?></td>
            <td><?= e($t['payee']) ?></td>
            <td><?= taka($t['amount']) ?></td>
            <td><?= taka($t['gateway_fee']) ?></td>
            <td><span class="pill pill-gray"><?= ucfirst($t['payment_method']) ?></span></td>
            <td><span class="pill pill-green"><?= ucfirst($t['status']) ?></span></td>
            <td><?= date('M d, Y h:i A', strtotime($t['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$loanTx): ?><tr><td colspan="8" style="color:var(--text-muted);">No loan transactions yet.</td></tr><?php endif; ?>
        </table>
      </div>

      <div class="admin-table-card">
        <div class="head"><h3>Donations</h3></div>
        <table class="admin-table">
          <tr><th>Txn Ref</th><th>Donor</th><th>Campaign</th><th>Amount</th><th>Date</th></tr>
          <?php foreach ($donationTx as $d): ?>
          <tr>
            <td><?= e($d['txn_ref']) ?></td>
            <td><?= $d['anonymous'] ? 'Anonymous' : e($d['donor']) ?></td>
            <td><?= e($d['campaign_title']) ?></td>
            <td><?= taka($d['amount']) ?></td>
            <td><?= date('M d, Y h:i A', strtotime($d['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$donationTx): ?><tr><td colspan="5" style="color:var(--text-muted);">No donations yet.</td></tr><?php endif; ?>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
