<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$admin = current_admin();
$active = 'users.php';

$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['nid_approve'])) {
        $pdo->prepare("UPDATE users SET nid_status='verified', nid_reject_reason=NULL WHERE id=? AND role='user'")->execute([$id]);
        flash('success', 'NID approved. The student can now borrow, lend and start campaigns.');
    } elseif (isset($_POST['nid_reject'])) {
        $reason = trim($_POST['reason'] ?? '') ?: 'Photos unclear or details do not match.';
        $pdo->prepare("UPDATE users SET nid_status='rejected', nid_reject_reason=? WHERE id=? AND role='user'")->execute([mb_substr($reason, 0, 250), $id]);
        flash('success', 'NID rejected. The student has been asked to re-submit.');
    } elseif (isset($_POST['field'])) {
        // individually verify each credential
        $allowed = ['uiu_id_verified', 'email_verified', 'phone_verified', 'payment_linked'];
        $field = $_POST['field'];
        if (in_array($field, $allowed, true)) {
            $pdo->prepare("UPDATE users SET $field = 1 - $field WHERE id=? AND role='user'")->execute([$id]);
            flash('success', 'Credential status updated.');
        }
    } elseif (isset($_POST['verify_all'])) {
        $pdo->prepare("UPDATE users SET uiu_id_verified=1, email_verified=1, phone_verified=1 WHERE id=? AND role='user'")->execute([$id]);
        flash('success', 'All credentials marked as verified.');
    } elseif (isset($_POST['toggle_status'])) {
        $pdo->prepare("UPDATE users SET status = IF(status='active','suspended','active') WHERE id=? AND role='user'")->execute([$id]);
        flash('success', 'Account status updated.');
    }
    redirect('view_user.php?id=' . $id);
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id=? AND role='user'");
$stmt->execute([$id]);
$us = $stmt->fetch();
if (!$us) { http_response_code(404); die('User not found.'); }

/* ---- activity for review ---- */
$posts = $pdo->prepare("SELECT * FROM loan_posts WHERE user_id=? ORDER BY created_at DESC");
$posts->execute([$id]); $posts = $posts->fetchAll();

$camps = $pdo->prepare("SELECT * FROM campaigns WHERE organizer_id=? ORDER BY created_at DESC");
$camps->execute([$id]); $camps = $camps->fetchAll();

$agreements = $pdo->prepare("SELECT a.*, bu.full_name borrower_name, lu.full_name lender_name
    FROM agreements a JOIN users bu ON bu.id=a.borrower_id JOIN users lu ON lu.id=a.lender_id
    WHERE a.borrower_id=? OR a.lender_id=? ORDER BY a.created_at DESC");
$agreements->execute([$id, $id]); $agreements = $agreements->fetchAll();

$txns = $pdo->prepare("SELECT t.*, u1.full_name payer, u2.full_name payee FROM transactions t
    JOIN users u1 ON u1.id=t.payer_id JOIN users u2 ON u2.id=t.payee_id
    WHERE t.payer_id=? OR t.payee_id=? ORDER BY t.created_at DESC LIMIT 25");
$txns->execute([$id, $id]); $txns = $txns->fetchAll();

$reviews = $pdo->prepare("SELECT r.*, u.full_name reviewer FROM reviews r JOIN users u ON u.id=r.reviewer_id
    WHERE r.reviewee_id=? ORDER BY r.created_at DESC");
$reviews->execute([$id]); $reviews = $reviews->fetchAll();

$totalBorrowed = $pdo->prepare("SELECT COALESCE(SUM(t.amount),0) s FROM transactions t JOIN agreements a ON a.id=t.agreement_id WHERE a.borrower_id=?");
$totalBorrowed->execute([$id]); $totalBorrowed = $totalBorrowed->fetch()['s'];

$totalLent = $pdo->prepare("SELECT COALESCE(SUM(t.amount),0) s FROM transactions t JOIN agreements a ON a.id=t.agreement_id WHERE a.lender_id=?");
$totalLent->execute([$id]); $totalLent = $totalLent->fetch()['s'];

$totalDonated = $pdo->prepare("SELECT COALESCE(SUM(amount),0) s FROM donations WHERE donor_id=?");
$totalDonated->execute([$id]); $totalDonated = $totalDonated->fetch()['s'];

$verifiedCount = $us['uiu_id_verified'] + $us['email_verified'] + $us['phone_verified'] + $us['payment_linked'] + ($us['nid_status'] === 'verified' ? 1 : 0);

$page_title = $us['full_name'];
$base = '../';
$use_admin_css = true;
include __DIR__ . '/../includes/header.php';
?>
<div class="admin-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1><a href="users.php" style="color:var(--text-muted);"><i class="fa-solid fa-arrow-left"></i></a> &nbsp;User Credentials Review</h1>
      <div class="table-actions">
        <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="verify_all" value="1"><button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-circle-check"></i> Verify All</button></form>
        <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="toggle_status" value="1"><button class="btn btn-outline btn-sm" type="submit" style="color:<?= $us['status']==='active'?'#dc2626':'#15803d' ?>;"><?= $us['status']==='active'?'Suspend Account':'Reactivate Account' ?></button></form>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

      <!-- Identity header -->
      <div class="admin-table-card" style="margin-bottom:20px;">
        <div style="padding:22px;display:flex;gap:18px;align-items:center;flex-wrap:wrap;">
          <img src="<?= e($us['avatar']) ?>" style="width:72px;height:72px;border-radius:50%;object-fit:cover;">
          <div style="flex:1;min-width:220px;">
            <h2 style="margin:0 0 5px;font-size:19px;">
              <?= e($us['full_name']) ?>
              <?= $us['uiu_id_verified'] ? '<span class="pill pill-green">UIU ID Verified</span>' : '<span class="pill pill-orange">UIU ID Unverified</span>' ?>
              <?= nid_status_pill($us['nid_status']) ?>
              <?= $us['status']==='suspended' ? '<span class="pill pill-red">Suspended</span>' : '' ?>
            </h2>
            <div style="font-size:13px;color:var(--text-muted);">
              <?= e($us['department']) ?> · Batch <?= e($us['batch']) ?> · Joined <?= date('M d, Y', strtotime($us['created_at'])) ?>
            </div>
          </div>
          <div style="text-align:right;">
            <div style="font-size:24px;font-weight:800;color:var(--orange);"><?= $verifiedCount ?>/5</div>
            <div style="font-size:12px;color:var(--text-muted);">credentials verified</div>
          </div>
        </div>
      </div>

      <div class="kpi-row">
        <div class="kpi-card"><div class="kpi-head"><div class="icon" style="background:#fff3e8;color:#f97316;"><i class="fa-solid fa-star"></i></div><div class="kpi-title">Trust Score</div></div><div class="num"><?= number_format($us['trust_score'],1) ?> <span style="font-size:14px;color:var(--text-muted);">/ 5</span></div></div>
        <div class="kpi-card"><div class="kpi-head"><div class="icon" style="background:#eff6ff;color:#2563eb;"><i class="fa-solid fa-arrow-down"></i></div><div class="kpi-title">Total Borrowed</div></div><div class="num"><?= taka($totalBorrowed) ?></div></div>
        <div class="kpi-card"><div class="kpi-head"><div class="icon" style="background:#f0fdf4;color:#16a34a;"><i class="fa-solid fa-arrow-up"></i></div><div class="kpi-title">Total Lent</div></div><div class="num"><?= taka($totalLent) ?></div></div>
        <div class="kpi-card"><div class="kpi-head"><div class="icon" style="background:#fdf2f8;color:#db2777;"><i class="fa-solid fa-heart"></i></div><div class="kpi-title">Total Donated</div></div><div class="num"><?= taka($totalDonated) ?></div></div>
      </div>

      <div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap;">
        <div style="flex:1;min-width:340px;display:flex;flex-direction:column;gap:20px;">

          <!-- ================= NID REVIEW ================= -->
          <div class="admin-table-card" id="nid" style="border-color:<?= $us['nid_status']==='pending' ? '#fdba74' : 'var(--border)' ?>;">
            <div class="head"><h3><i class="fa-solid fa-address-card"></i> National ID (NID) Review</h3><?= nid_status_pill($us['nid_status']) ?></div>
            <div style="padding:20px;">
              <?php if ($us['nid_status'] === 'not_submitted' && !$us['nid_front']): ?>
                <p style="font-size:13.5px;color:var(--text-muted);margin:0;">This student has not uploaded NID photos yet.
                  <?php if ($us['nid_number']): ?>Number given at sign-up: <strong><?= e($us['nid_number']) ?></strong><?php endif; ?></p>
              <?php else: ?>
                <div class="info-row"><span>NID number</span><span class="val" style="font-family:monospace;font-size:14px;"><?= e($us['nid_number'] ?: '—') ?></span></div>
                <div class="info-row"><span>Date of birth</span><span class="val"><?= $us['date_of_birth'] ? date('M d, Y', strtotime($us['date_of_birth'])) : '—' ?></span></div>
                <div class="info-row"><span>Address</span><span class="val" style="max-width:65%;text-align:right;"><?= e($us['address'] ?: '—') ?></span></div>
                <div class="info-row"><span>Submitted</span><span class="val"><?= $us['nid_submitted_at'] ? date('M d, Y h:i A', strtotime($us['nid_submitted_at'])) : '—' ?></span></div>
                <?php if ($us['nid_status'] === 'rejected' && $us['nid_reject_reason']): ?>
                  <div class="info-row"><span>Rejected because</span><span class="val" style="color:#b91c1c;"><?= e($us['nid_reject_reason']) ?></span></div>
                <?php endif; ?>

                <div style="display:flex;gap:14px;margin-top:16px;flex-wrap:wrap;">
                  <?php foreach (['nid_front' => 'Front', 'nid_back' => 'Back'] as $col => $lbl): ?>
                    <div style="flex:1;min-width:220px;">
                      <div style="font-size:12.5px;font-weight:700;margin-bottom:6px;"><?= $lbl ?> of NID</div>
                      <?php if ($us[$col]): ?>
                        <?php if (preg_match('/\.pdf$/i', $us[$col])): ?>
                          <a class="btn btn-outline btn-sm" href="file.php?t=<?= $col ?>&id=<?= $us['id'] ?>" target="_blank"><i class="fa-solid fa-file-pdf"></i> Open PDF</a>
                        <?php else: ?>
                          <a href="file.php?t=<?= $col ?>&id=<?= $us['id'] ?>" target="_blank"><img class="file-thumb" src="file.php?t=<?= $col ?>&id=<?= $us['id'] ?>" alt="NID <?= $lbl ?>"></a>
                        <?php endif; ?>
                      <?php else: ?>
                        <div style="padding:26px 10px;text-align:center;font-size:12px;color:var(--text-muted);background:#fafafa;border:1px dashed var(--border);border-radius:10px;">No photo on file<br>(demo record)</div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>

                <div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap;align-items:flex-start;">
                  <?php if ($us['nid_status'] !== 'verified'): ?>
                  <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="nid_approve" value="1">
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-circle-check"></i> Approve NID</button></form>
                  <?php endif; ?>
                  <form method="post" style="display:flex;gap:8px;flex:1;min-width:260px;">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="nid_reject" value="1">
                    <input type="text" name="reason" placeholder="Reason (e.g. photo blurry, name mismatch)" style="flex:1;border:1px solid var(--border);border-radius:10px;padding:10px 12px;font-size:13px;">
                    <button class="btn btn-outline" type="submit" style="color:#dc2626;"><i class="fa-solid fa-circle-xmark"></i> <?= $us['nid_status']==='verified' ? 'Revoke' : 'Reject' ?></button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- THE credentials table -->
          <div class="admin-table-card">
            <div class="head"><h3><i class="fa-solid fa-id-card"></i> Submitted Credentials</h3></div>
            <table class="admin-table">
              <tr><th>Credential</th><th>Value on record</th><th>Status</th><th>Action</th></tr>
              <tr>
                <td><strong>UIU Student ID</strong></td>
                <td style="font-family:monospace;font-size:13px;"><?= e($us['student_id']) ?></td>
                <td><span class="pill <?= $us['uiu_id_verified']?'pill-green':'pill-orange' ?>"><?= $us['uiu_id_verified']?'Verified':'Pending' ?></span></td>
                <td><form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="field" value="uiu_id_verified"><button class="link-btn" type="submit"><?= $us['uiu_id_verified']?'Revoke':'Verify' ?></button></form></td>
              </tr>
              <tr>
                <td><strong>University Email</strong></td>
                <td style="font-family:monospace;font-size:13px;"><?= e($us['email']) ?></td>
                <td><span class="pill <?= $us['email_verified']?'pill-green':'pill-orange' ?>"><?= $us['email_verified']?'Verified':'Pending' ?></span></td>
                <td><form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="field" value="email_verified"><button class="link-btn" type="submit"><?= $us['email_verified']?'Revoke':'Verify' ?></button></form></td>
              </tr>
              <tr>
                <td><strong>Phone Number</strong></td>
                <td style="font-family:monospace;font-size:13px;"><?= e($us['phone'] ?: '— not provided —') ?></td>
                <td><span class="pill <?= $us['phone_verified']?'pill-green':'pill-orange' ?>"><?= $us['phone_verified']?'Verified':'Pending' ?></span></td>
                <td><form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="field" value="phone_verified"><button class="link-btn" type="submit"><?= $us['phone_verified']?'Revoke':'Verify' ?></button></form></td>
              </tr>
              <tr>
                <td><strong>Payment Account</strong></td>
                <td style="color:var(--text-muted);">bKash / Nagad / Card</td>
                <td><span class="pill <?= $us['payment_linked']?'pill-green':'pill-orange' ?>"><?= $us['payment_linked']?'Linked':'Not linked' ?></span></td>
                <td><form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="field" value="payment_linked"><button class="link-btn" type="submit"><?= $us['payment_linked']?'Unlink':'Mark linked' ?></button></form></td>
              </tr>
              <tr>
                <td><strong>Department / Batch</strong></td>
                <td><?= e($us['department']) ?> · Batch <?= e($us['batch']) ?></td>
                <td><span class="pill pill-gray">Self-declared</span></td>
                <td>—</td>
              </tr>
              <tr>
                <td><strong>Agreement Policy</strong></td>
                <td><?= $us['agreement_accepted'] ? 'Accepted on ' . date('M d, Y', strtotime($us['created_at'])) : 'Not accepted' ?></td>
                <td><span class="pill <?= $us['agreement_accepted']?'pill-green':'pill-orange' ?>"><?= $us['agreement_accepted']?'Accepted':'Pending' ?></span></td>
                <td>—</td>
              </tr>
            </table>
          </div>

          <!-- This user's posts -->
          <div class="admin-table-card">
            <div class="head"><h3>Loan Posts by this user (<?= count($posts) ?>)</h3></div>
            <table class="admin-table">
              <tr><th>Amount</th><th>Purpose</th><th>Status</th><th>Moderation</th><th>Posted</th><th></th></tr>
              <?php foreach ($posts as $p): ?>
              <tr>
                <td><?= taka($p['amount']) ?></td>
                <td><?= e($p['purpose']) ?></td>
                <td><span class="pill pill-gray"><?= ucfirst($p['status']) ?></span></td>
                <td><?php $m=['approved'=>'pill-green','pending'=>'pill-orange','removed'=>'pill-red'][$p['admin_status']]; echo "<span class=\"pill $m\">".ucfirst($p['admin_status'])."</span>"; ?></td>
                <td><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
                <td><a href="view_post.php?id=<?= $p['id'] ?>" class="link-btn">View</a></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$posts): ?><tr><td colspan="6" style="color:var(--text-muted);">No loan posts.</td></tr><?php endif; ?>
            </table>
          </div>

          <!-- This user's campaigns -->
          <div class="admin-table-card">
            <div class="head"><h3>Campaigns by this user (<?= count($camps) ?>)</h3></div>
            <table class="admin-table">
              <tr><th>Title</th><th>Raised / Goal</th><th>Status</th><th>Created</th><th></th></tr>
              <?php foreach ($camps as $cp): ?>
              <tr>
                <td><?= e($cp['title']) ?></td>
                <td><?= taka($cp['raised_amount']) ?> / <?= taka($cp['goal_amount']) ?></td>
                <td><?php $m=['verified'=>'pill-green','pending'=>'pill-orange','rejected'=>'pill-red'][$cp['admin_status']]; echo "<span class=\"pill $m\">".ucfirst($cp['admin_status'])."</span>"; ?></td>
                <td><?= date('M d, Y', strtotime($cp['created_at'])) ?></td>
                <td><a href="view_campaign.php?id=<?= $cp['id'] ?>" class="link-btn">View</a></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$camps): ?><tr><td colspan="5" style="color:var(--text-muted);">No campaigns.</td></tr><?php endif; ?>
            </table>
          </div>

          <!-- Agreements -->
          <div class="admin-table-card">
            <div class="head"><h3>Agreements (<?= count($agreements) ?>)</h3></div>
            <table class="admin-table">
              <tr><th>Role</th><th>Counterparty</th><th>Principal</th><th>Status</th><th>Date</th></tr>
              <?php foreach ($agreements as $a): $isBorrower = $a['borrower_id'] == $id; ?>
              <tr>
                <td><span class="pill pill-gray"><?= $isBorrower ? 'Borrower' : 'Lender' ?></span></td>
                <td><?= e($isBorrower ? $a['lender_name'] : $a['borrower_name']) ?></td>
                <td><?= taka($a['principal']) ?></td>
                <td><span class="pill <?= $a['status']==='completed'?'pill-green':($a['status']==='disputed'?'pill-red':'pill-orange') ?>"><?= ucwords(str_replace('_',' ',$a['status'])) ?></span></td>
                <td><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$agreements): ?><tr><td colspan="5" style="color:var(--text-muted);">No agreements.</td></tr><?php endif; ?>
            </table>
          </div>

          <!-- Transactions -->
          <div class="admin-table-card">
            <div class="head"><h3>Transaction History (<?= count($txns) ?>)</h3></div>
            <table class="admin-table">
              <tr><th>Txn Ref</th><th>Direction</th><th>Amount</th><th>Method</th><th>Date</th></tr>
              <?php foreach ($txns as $t): ?>
              <tr>
                <td><?= e($t['txn_ref']) ?></td>
                <td><?= $t['payer_id'] == $id ? 'Paid to ' . e($t['payee']) : 'Received from ' . e($t['payer']) ?></td>
                <td><?= taka($t['amount']) ?></td>
                <td><span class="pill pill-gray"><?= ucfirst($t['payment_method']) ?></span></td>
                <td><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$txns): ?><tr><td colspan="5" style="color:var(--text-muted);">No transactions.</td></tr><?php endif; ?>
            </table>
          </div>
        </div>

        <!-- Side: account meta + reviews -->
        <div style="width:320px;flex-shrink:0;display:flex;flex-direction:column;gap:20px;">
          <div class="admin-table-card">
            <div class="head"><h3>Account Record</h3></div>
            <div style="padding:18px;">
              <div class="info-row"><span>Internal User ID</span><span class="val">#<?= $us['id'] ?></span></div>
              <div class="info-row"><span>Role</span><span class="val"><?= ucfirst($us['role']) ?></span></div>
              <div class="info-row"><span>Account Status</span><span class="val <?= $us['status']==='active'?'green':'' ?>"><?= ucfirst($us['status']) ?></span></div>
              <div class="info-row"><span>Registered</span><span class="val"><?= date('M d, Y', strtotime($us['created_at'])) ?></span></div>
              <div class="info-row"><span>Password</span><span class="val" style="color:var(--text-muted);">Hashed (bcrypt)</span></div>
              <p style="font-size:11.5px;color:var(--text-muted);margin:12px 0 0;line-height:1.5;">
                Passwords are one-way hashed and can never be viewed by an administrator. To help a locked-out
                student, ask them to use the password reset flow.
              </p>
            </div>
          </div>

          <div class="admin-table-card">
            <div class="head"><h3>Reviews Received (<?= count($reviews) ?>)</h3></div>
            <div style="padding:6px 18px 18px;">
              <?php foreach (array_slice($reviews, 0, 6) as $r): ?>
                <div class="review-item">
                  <div class="who"><span><?= e($r['reviewer']) ?></span><span style="font-weight:400;color:var(--text-muted);font-size:11.5px;"><?= date('M d, Y', strtotime($r['created_at'])) ?></span></div>
                  <div class="stars"><?= str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']) ?></div>
                  <?php if ($r['comment']): ?><div class="comment">"<?= e($r['comment']) ?>"</div><?php endif; ?>
                </div>
              <?php endforeach; ?>
              <?php if (!$reviews): ?><p style="color:var(--text-muted);font-size:13px;">No reviews yet.</p><?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
