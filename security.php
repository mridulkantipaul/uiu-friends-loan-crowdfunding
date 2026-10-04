<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$u = current_user();
$active = 'security.php';

$nidErrors = [];

/* ---------- Submit / re-submit NID ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_nid'])) {
    csrf_check();
    if ($u['nid_status'] === 'verified') {
        $nidErrors[] = 'Your NID is already verified and locked. Contact an admin if it must be changed.';
    } else {
        $nid  = preg_replace('/\s+/', '', trim($_POST['nid_number'] ?? ''));
        $dob  = trim($_POST['date_of_birth'] ?? '');
        $addr = trim($_POST['address'] ?? '');

        if (!nid_is_valid_format($nid)) $nidErrors[] = 'Enter a valid NID number (10, 13 or 17 digits).';

        $dobTs = strtotime($dob);
        if (!$dob || !$dobTs) {
            $nidErrors[] = 'Please enter your date of birth.';
        } elseif ($dobTs > strtotime('-16 years') || $dobTs < strtotime('-80 years')) {
            $nidErrors[] = 'Date of birth does not look valid for a university student.';
        }
        if (mb_strlen($addr) < 10) $nidErrors[] = 'Please enter your full present/permanent address (at least 10 characters).';

        $chk = $pdo->prepare("SELECT id FROM users WHERE nid_number = ? AND id <> ?");
        $chk->execute([$nid, $u['id']]);
        if ($chk->fetch()) $nidErrors[] = 'This NID is already registered with another account.';

        $front = $_FILES['nid_front'] ?? null;
        $back  = $_FILES['nid_back'] ?? null;
        if (!$front || ($msg = validate_upload($front))) $nidErrors[] = 'NID front photo: ' . ($msg ?? 'required.');
        if (!$back  || ($msg = validate_upload($back)))  $nidErrors[] = 'NID back photo: ' . ($msg ?? 'required.');

        if (!$nidErrors) {
            $fp = save_upload($front, 'nid');
            $bp = save_upload($back, 'nid');
            if (!$fp || !$bp) {
                delete_upload($fp); delete_upload($bp);
                $nidErrors[] = 'Could not save the uploaded photos. Please check that the uploads folder is writable.';
            } else {
                delete_upload($u['nid_front']); delete_upload($u['nid_back']);   // replace older photos
                $pdo->prepare("UPDATE users SET nid_number=?, date_of_birth=?, address=?, nid_front=?, nid_back=?,
                               nid_status='pending', nid_reject_reason=NULL, nid_submitted_at=NOW() WHERE id=?")
                    ->execute([$nid, date('Y-m-d', $dobTs), $addr, $fp, $bp, $u['id']]);
                flash('success', 'NID submitted. An admin will review it shortly — you will be able to borrow, lend and start campaigns once it is verified.');
                redirect('security.php#nid');
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_verification'])) {
    csrf_check();
    flash('success', 'Your documents were submitted. An admin will verify your UIU ID shortly.');
    redirect('security.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    csrf_check();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    if (!password_verify($current, $u['password_hash'])) {
        $pwError = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $pwError = 'New password must be at least 6 characters.';
    } else {
        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        flash('success', 'Password updated successfully.');
        redirect('security.php');
    }
}

$nidStatus = $u['nid_status'];
$canEditNid = $nidStatus !== 'verified';

$page_title = 'Security & Verification';
include __DIR__ . '/includes/header.php';
?>
<div class="app-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main-area">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <div class="content-wrap">
      <div class="content-main">
        <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

        <!-- ================= NID VERIFICATION ================= -->
        <div class="card" id="nid">
          <h3><i class="fa-solid fa-address-card"></i> National ID (NID) Verification
            <span style="float:right;"><?= nid_status_pill($nidStatus) ?></span>
          </h3>

          <div class="alert alert-info" style="margin-bottom:16px;">
            <strong>Why we need your NID:</strong> every loan is linked to a real, identifiable person. This keeps borrowers
            accountable, makes it possible to take legal action if someone runs away with a loan, and protects lenders and donors.
            Your NID is visible only to you and the platform administrators — other students only see a masked number.
          </div>

          <?php if ($nidStatus === 'verified'): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> Your NID is verified. You can request loans, make offers and start campaigns.</div>
            <div class="info-row"><span>NID Number</span><span class="val"><?= e(mask_nid($u['nid_number'])) ?></span></div>
            <div class="info-row"><span>Date of Birth</span><span class="val"><?= $u['date_of_birth'] ? date('M d, Y', strtotime($u['date_of_birth'])) : '—' ?></span></div>
            <div class="info-row"><span>Address</span><span class="val" style="text-align:right;max-width:60%;"><?= e($u['address'] ?: '—') ?></span></div>
          <?php else: ?>
            <?php if ($nidStatus === 'pending'): ?>
              <div class="alert alert-info"><i class="fa-solid fa-clock"></i> Your NID was submitted <?= $u['nid_submitted_at'] ? time_ago($u['nid_submitted_at']) : '' ?> and is waiting for admin review. You may re-submit below if you made a mistake.</div>
            <?php elseif ($nidStatus === 'rejected'): ?>
              <div class="alert alert-error"><i class="fa-solid fa-circle-xmark"></i> Your NID was rejected<?= $u['nid_reject_reason'] ? ': <strong>' . e($u['nid_reject_reason']) . '</strong>' : '.' ?> Please re-submit clear, correct photos.</div>
            <?php endif; ?>

            <?php foreach ($nidErrors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="submit_nid" value="1">
              <div class="form-row">
                <div class="form-group">
                  <label>NID Number</label>
                  <input type="text" name="nid_number" inputmode="numeric" value="<?= e($_POST['nid_number'] ?? $u['nid_number']) ?>" placeholder="10, 13 or 17 digits" required>
                </div>
                <div class="form-group">
                  <label>Date of Birth (as on NID)</label>
                  <input type="date" name="date_of_birth" value="<?= e($_POST['date_of_birth'] ?? $u['date_of_birth']) ?>" required>
                </div>
              </div>
              <div class="form-group">
                <label>Full Address (present / permanent)</label>
                <textarea name="address" rows="2" placeholder="House, road, area, district" required><?= e($_POST['address'] ?? $u['address']) ?></textarea>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>NID — Front photo</label>
                  <input type="file" name="nid_front" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>
                <div class="form-group">
                  <label>NID — Back photo</label>
                  <input type="file" name="nid_back" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>
              </div>
              <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">JPG, PNG or PDF · max 4 MB each · make sure all text is clearly readable.</p>
              <button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Submit NID for Verification</button>
            </form>
          <?php endif; ?>
        </div>

        <div class="card">
          <h3><i class="fa-solid fa-id-card"></i> UIU ID Verification</h3>
          <?php if ($u['uiu_id_verified']): ?>
            <div class="alert alert-success">Your UIU Student ID is verified.</div>
          <?php else: ?>
            <div class="alert alert-info">Verification is pending. Upload your Student ID card to get verified by an admin.</div>
            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="submit_verification" value="1">
              <div class="form-group"><label>Upload Student ID (image/PDF)</label><input type="file" name="id_doc"></div>
              <button class="btn btn-primary" type="submit">Submit for Verification</button>
            </form>
          <?php endif; ?>
        </div>

        <div class="card">
          <h3>Verification Checklist</h3>
          <ul class="checklist">
            <li class="<?= $nidStatus==='verified'?'':'pending' ?>"><i class="fa-solid fa-circle-<?= $nidStatus==='verified'?'check':'exclamation' ?>"></i> National ID (NID) — <?= e(nid_status_label($nidStatus)) ?></li>
            <li class="<?= $u['uiu_id_verified']?'':'pending' ?>"><i class="fa-solid fa-circle-<?= $u['uiu_id_verified']?'check':'exclamation' ?>"></i> UIU Student ID</li>
            <li class="<?= $u['email_verified']?'':'pending' ?>"><i class="fa-solid fa-circle-<?= $u['email_verified']?'check':'exclamation' ?>"></i> University Email</li>
            <li class="<?= $u['phone_verified']?'':'pending' ?>"><i class="fa-solid fa-circle-<?= $u['phone_verified']?'check':'exclamation' ?>"></i> Phone Number</li>
            <li class="<?= $u['payment_linked']?'':'pending' ?>"><i class="fa-solid fa-circle-<?= $u['payment_linked']?'check':'exclamation' ?>"></i> Payment Account Linked</li>
          </ul>
        </div>

        <div class="card">
          <h3><i class="fa-solid fa-key"></i> Change Password</h3>
          <?php if (!empty($pwError)): ?><div class="alert alert-error"><?= e($pwError) ?></div><?php endif; ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="change_password" value="1">
            <div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div>
            <div class="form-group"><label>New Password</label><input type="password" name="new_password" required></div>
            <button class="btn btn-primary" type="submit">Update Password</button>
          </form>
        </div>
      </div>

      <div class="content-side">
        <div class="safety-box">
          <i class="fa-solid fa-shield-halved"></i>
          <h4>Stay Safe &amp; Secure</h4>
          <p>We verify every student ID and NID and use digital agreements to protect you from fraud. Never share OTPs, passwords, or bank PINs with anyone.</p>
        </div>
        <div class="card">
          <h3>Agreement Policy</h3>
          <p style="font-size:12.5px;color:var(--text-muted);">You accepted the Lender &amp; Borrower Agreement Policy on <?= date('M d, Y', strtotime($u['created_at'])) ?>.</p>
          <a href="report.php" class="btn btn-outline btn-block btn-sm"><i class="fa-solid fa-flag"></i> Report an issue</a>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
