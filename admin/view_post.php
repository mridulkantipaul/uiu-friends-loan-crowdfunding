<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$admin = current_admin();
$active = 'loan_posts.php';

$id = (int)($_GET['id'] ?? 0);

// moderation actions straight from the detail view
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['approve'])) $pdo->prepare("UPDATE loan_posts SET admin_status='approved' WHERE id=?")->execute([$id]);
    if (isset($_POST['remove']))  $pdo->prepare("UPDATE loan_posts SET admin_status='removed', status='closed' WHERE id=?")->execute([$id]);
    flash('success', 'Loan post moderation updated.');
    redirect('view_post.php?id=' . $id);
}

$stmt = $pdo->prepare("SELECT lp.*, u.id uid, u.full_name, u.email, u.student_id, u.department, u.batch,
        u.phone, u.avatar, u.trust_score, u.uiu_id_verified, u.status user_status, u.created_at joined
    FROM loan_posts lp JOIN users u ON u.id = lp.user_id WHERE lp.id = ?");
$stmt->execute([$id]);
$post = $stmt->fetch();
if (!$post) { http_response_code(404); die('Loan post not found.'); }

$offers = $pdo->prepare("SELECT lo.*, u.full_name, u.email, u.avatar, u.trust_score
    FROM loan_offers lo JOIN users u ON u.id = lo.lender_id
    WHERE lo.loan_post_id = ? ORDER BY lo.created_at DESC");
$offers->execute([$id]);
$offers = $offers->fetchAll();

$agreement = $pdo->prepare("SELECT a.*, bu.full_name borrower_name, lu.full_name lender_name
    FROM agreements a JOIN users bu ON bu.id=a.borrower_id JOIN users lu ON lu.id=a.lender_id
    WHERE a.loan_post_id = ?");
$agreement->execute([$id]);
$agreement = $agreement->fetch();

$installments = [];
$txns = [];
if ($agreement) {
    $s = $pdo->prepare("SELECT * FROM installments WHERE agreement_id=? ORDER BY installment_no");
    $s->execute([$agreement['id']]);
    $installments = $s->fetchAll();

    $s = $pdo->prepare("SELECT * FROM transactions WHERE agreement_id=? ORDER BY created_at DESC");
    $s->execute([$agreement['id']]);
    $txns = $s->fetchAll();
}

$page_title = 'Loan Post #' . $id;
$base = '../';
$use_admin_css = true;
include __DIR__ . '/../includes/header.php';
?>
<div class="admin-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1><a href="loan_posts.php" style="color:var(--text-muted);"><i class="fa-solid fa-arrow-left"></i></a> &nbsp;Loan Post #<?= $id ?></h1>
      <div class="table-actions">
        <?php if ($post['admin_status'] !== 'approved'): ?>
          <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="approve" value="1"><button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-check"></i> Approve</button></form>
        <?php endif; ?>
        <?php if ($post['admin_status'] !== 'removed'): ?>
          <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="remove" value="1"><button class="btn btn-outline btn-sm" type="submit" style="color:#dc2626;"><i class="fa-solid fa-ban"></i> Remove</button></form>
        <?php endif; ?>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

      <div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap;">
        <div style="flex:1;min-width:340px;display:flex;flex-direction:column;gap:20px;">

          <!-- The post itself, exactly as students see it -->
          <div class="admin-table-card">
            <div class="head"><h3>Post Content (as students see it)</h3>
              <?php
                $m = ['approved'=>'pill-green','pending'=>'pill-orange','removed'=>'pill-red'][$post['admin_status']];
                echo "<span class=\"pill $m\">" . ucfirst($post['admin_status']) . "</span>";
              ?>
            </div>
            <div style="padding:20px;">
              <div class="post-amount" style="margin-top:0;"><?= taka($post['amount']) ?> <span>requested</span></div>
              <div class="post-desc"><?= nl2br(e($post['description'])) ?></div>
              <div class="tag-row">
                <?php if ($post['emi_ok']): ?><span class="tag">EMI OK</span><?php endif; ?>
                <span class="tag"><?= e($post['repay_period_text']) ?></span>
                <span class="tag">Purpose: <?= e($post['purpose']) ?></span>
                <span class="tag">Status: <?= ucfirst($post['status']) ?></span>
              </div>
              <p style="font-size:12px;color:var(--text-muted);margin:0;">Posted <?= date('M d, Y \a\t h:i A', strtotime($post['created_at'])) ?></p>
            </div>
          </div>

          <!-- Offers received -->
          <div class="admin-table-card">
            <div class="head"><h3>Offers Received (<?= count($offers) ?>)</h3></div>
            <table class="admin-table">
              <tr><th>Lender</th><th>Amount</th><th>Interest</th><th>Tenure</th><th>Status</th><th>Sent</th></tr>
              <?php foreach ($offers as $o): ?>
              <tr>
                <td><div class="mini-user"><img src="<?= e($o['avatar']) ?>"><div><div class="n"><?= e($o['full_name']) ?></div><div class="s"><?= e($o['email']) ?></div></div></div></td>
                <td><?= taka($o['amount']) ?></td>
                <td><?= $o['interest_rate'] > 0 ? $o['interest_rate'].'%' : 'No interest' ?></td>
                <td><?= $o['tenure_months'] ?> mo</td>
                <td><?php
                  $m = ['accepted'=>'pill-green','pending'=>'pill-orange','rejected'=>'pill-gray','withdrawn'=>'pill-gray'][$o['status']];
                  echo "<span class=\"pill $m\">" . ucfirst($o['status']) . "</span>"; ?></td>
                <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$offers): ?><tr><td colspan="6" style="color:var(--text-muted);">No offers on this post yet.</td></tr><?php endif; ?>
            </table>
          </div>

          <?php if ($agreement): ?>
          <div class="admin-table-card">
            <div class="head"><h3>Resulting Agreement #<?= $agreement['id'] ?></h3>
              <span class="pill <?= $agreement['status']==='completed'?'pill-green':($agreement['status']==='disputed'?'pill-red':'pill-orange') ?>"><?= ucwords(str_replace('_',' ',$agreement['status'])) ?></span>
            </div>
            <table class="admin-table">
              <tr><th>Borrower</th><th>Lender</th><th>Principal</th><th>Interest</th><th>Total Payable</th><th>Signatures</th></tr>
              <tr>
                <td><?= e($agreement['borrower_name']) ?></td>
                <td><?= e($agreement['lender_name']) ?></td>
                <td><?= taka($agreement['principal']) ?></td>
                <td><?= $agreement['interest_rate'] ?>%</td>
                <td><?= taka($agreement['total_payable']) ?></td>
                <td>
                  <span class="pill <?= $agreement['borrower_signed']?'pill-green':'pill-orange' ?>">B<?= $agreement['borrower_signed']?' ✓':' …' ?></span>
                  <span class="pill <?= $agreement['lender_signed']?'pill-green':'pill-orange' ?>">L<?= $agreement['lender_signed']?' ✓':' …' ?></span>
                </td>
              </tr>
            </table>
            <?php if ($installments): ?>
            <table class="admin-table" style="border-top:1px solid var(--border);">
              <tr><th>Installment</th><th>Due Date</th><th>Amount</th><th>Status</th></tr>
              <?php foreach ($installments as $ins): ?>
              <tr>
                <td><?= $ins['installment_no'] ?> of <?= count($installments) ?></td>
                <td><?= date('M d, Y', strtotime($ins['due_date'])) ?></td>
                <td><?= taka($ins['amount']) ?></td>
                <td><span class="pill <?= $ins['status']==='paid'?'pill-green':'pill-orange' ?>"><?= ucfirst($ins['status']) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </table>
            <?php endif; ?>
            <?php if ($txns): ?>
            <table class="admin-table" style="border-top:1px solid var(--border);">
              <tr><th>Txn Ref</th><th>Amount</th><th>Fee</th><th>Method</th><th>Date</th></tr>
              <?php foreach ($txns as $t): ?>
              <tr><td><?= e($t['txn_ref']) ?></td><td><?= taka($t['amount']) ?></td><td><?= taka($t['gateway_fee']) ?></td><td><span class="pill pill-gray"><?= ucfirst($t['payment_method']) ?></span></td><td><?= date('M d, Y h:i A', strtotime($t['created_at'])) ?></td></tr>
              <?php endforeach; ?>
            </table>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Borrower credentials sidebar -->
        <div style="width:320px;flex-shrink:0;display:flex;flex-direction:column;gap:20px;">
          <div class="admin-table-card">
            <div class="head"><h3>Posted By</h3></div>
            <div style="padding:18px;">
              <div class="mini-user" style="margin-bottom:14px;">
                <img src="<?= e($post['avatar']) ?>" style="width:46px;height:46px;">
                <div><div class="n" style="font-size:14px;"><?= e($post['full_name']) ?></div><div class="s"><?= e($post['email']) ?></div></div>
              </div>
              <div class="info-row"><span>Student ID</span><span class="val"><?= e($post['student_id']) ?></span></div>
              <div class="info-row"><span>Department</span><span class="val"><?= e($post['department']) ?> · '<?= substr($post['batch'],-2) ?></span></div>
              <div class="info-row"><span>Phone</span><span class="val"><?= e($post['phone'] ?: '—') ?></span></div>
              <div class="info-row"><span>Trust Score</span><span class="val orange"><?= number_format($post['trust_score'],1) ?> / 5</span></div>
              <div class="info-row"><span>UIU ID</span><span class="val <?= $post['uiu_id_verified']?'green':'' ?>"><?= $post['uiu_id_verified'] ? 'Verified' : 'Not verified' ?></span></div>
              <div class="info-row"><span>Account</span><span class="val"><?= ucfirst($post['user_status']) ?></span></div>
              <div class="info-row"><span>Joined</span><span class="val"><?= date('M d, Y', strtotime($post['joined'])) ?></span></div>
              <a href="view_user.php?id=<?= $post['uid'] ?>" class="btn btn-primary btn-block btn-sm" style="margin-top:14px;">
                <i class="fa-solid fa-id-card"></i> View Full Credentials
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
