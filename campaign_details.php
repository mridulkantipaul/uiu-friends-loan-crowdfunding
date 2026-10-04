<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$u = current_user();
$active = 'crowdfunding.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT c.*, u.id organizer_uid, u.full_name, u.department, u.batch, u.uiu_id_verified, u.nid_status, u.nid_number
    FROM campaigns c JOIN users u ON u.id = c.organizer_id WHERE c.id = ?");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) { http_response_code(404); die('Campaign not found.'); }
$isOrganizer = $c['organizer_uid'] == $u['id'];

$formError = null;
$docErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['donate'])) {
    csrf_check();
    $amount = (float)($_POST['amount'] ?? 0);
    $anon   = isset($_POST['anonymous']) ? 1 : 0;
    if ($amount >= 50) {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO donations (campaign_id, donor_id, amount, anonymous, txn_ref) VALUES (?,?,?,?,?)")
            ->execute([$id, $u['id'], $amount, $anon, gen_txn_ref()]);
        $pdo->prepare("UPDATE campaigns SET raised_amount = raised_amount + ? WHERE id=?")->execute([$amount, $id]);
        $pdo->commit();
        flash('success', 'Thank you! Your donation of ' . taka($amount) . ' was received.');
        redirect('campaign_details.php?id=' . $id . '#ledger');
    } else {
        $formError = 'Minimum donation amount is ৳50.';
    }
}

/* Organizer adds more credential documents later */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_docs']) && $isOrganizer) {
    csrf_check();
    [$docList, $docErrors] = collect_doc_uploads($_POST, $_FILES['doc_file'] ?? null);
    if (!$docList && !$docErrors) $docErrors[] = 'Please choose at least one document to upload.';
    if (!$docErrors) {
        $n = save_campaign_docs($pdo, $id, $docList);
        // new documents mean the admin needs to look again
        $pdo->prepare("UPDATE campaigns SET docs_verified = 0 WHERE id = ?")->execute([$id]);
        flash('success', $n . ' credential document' . ($n === 1 ? '' : 's') . ' added. Admins will review them shortly.');
        redirect('campaign_details.php?id=' . $id . '#credentials');
    }
}

$stmt = $pdo->prepare("SELECT c.*, u.id organizer_uid, u.full_name, u.department, u.batch, u.uiu_id_verified, u.nid_status, u.nid_number
    FROM campaigns c JOIN users u ON u.id = c.organizer_id WHERE c.id = ?");
$stmt->execute([$id]);
$c = $stmt->fetch();
$pct = min(100, round($c['raised_amount'] / max(1,$c['goal_amount']) * 100));

$donStmt = $pdo->prepare("SELECT d.*, u.full_name FROM donations d JOIN users u ON u.id = d.donor_id WHERE d.campaign_id=? ORDER BY d.created_at DESC");
$donStmt->execute([$id]);
$donations = $donStmt->fetchAll();

$docStmt = $pdo->prepare("SELECT * FROM campaign_documents WHERE campaign_id=? ORDER BY uploaded_at DESC, id DESC");
$docStmt->execute([$id]);
$docs = $docStmt->fetchAll();
$docTypes = campaign_doc_types();
$verifiedDocs = count(array_filter($docs, fn($d) => $d['status'] === 'verified'));

$page_title = $c['title'];
include __DIR__ . '/includes/header.php';
?>
<div class="app-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main-area">
    <div class="page-header">
      <a href="crowdfunding.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i></a>
      <h1>Campaign Details</h1>
    </div>

    <div class="detail-wrap">
      <div class="detail-main">
        <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

        <?php if ($c['image']): ?>
        <div class="post-image">
          <img src="<?= e($c['image']) ?>" alt="">
          <span class="image-badge <?= $c['admin_status']!=='verified'?'pending':'' ?>"><?= $c['admin_status']==='verified' ? 'Verified Campaign' : 'Pending Verification' ?></span>
        </div>
        <?php endif; ?>

        <div class="card">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
            <span class="tag"><?= e($c['category']) ?></span>
            <?php if (!$c['image']): ?>
              <?= $c['admin_status']==='verified' ? '<span class="badge-verified"><i class="fa-solid fa-circle-check"></i> Verified Campaign</span>' : '<span class="badge-pending"><i class="fa-solid fa-clock"></i> Pending Verification</span>' ?>
            <?php endif; ?>
          </div>
          <h2 style="margin:0 0 8px;font-size:20px;"><?= e($c['title']) ?></h2>
          <p style="font-size:13.5px;color:var(--text-muted);margin-bottom:16px;">Organized by <strong><?= e($c['full_name']) ?></strong> · <?= e($c['department']) ?> '<?= substr($c['batch'],-2) ?>
            <?= $c['nid_status']==='verified' ? '<span class="badge-verified"><i class="fa-solid fa-circle-check"></i> NID Verified</span>' : '' ?>
            <?= $c['uiu_id_verified'] ? '<span class="badge-verified"><i class="fa-solid fa-circle-check"></i> UIU ID Verified</span>' : '' ?></p>
          <p class="post-desc"><?= nl2br(e($c['description'])) ?></p>
        </div>

        <!-- ================= CREDENTIALS (public view) ================= -->
        <div class="card" id="credentials">
          <h3><i class="fa-solid fa-file-shield"></i> Authenticity &amp; Credentials</h3>

          <div class="tag-row">
            <span class="tag"><i class="fa-solid fa-address-card"></i> Organiser NID <?= $c['nid_status']==='verified' ? '✓' : '…' ?></span>
            <span class="tag"><i class="fa-solid fa-id-card"></i> UIU Student ID <?= $c['uiu_id_verified'] ? '✓' : '…' ?></span>
            <span class="tag"><i class="fa-solid fa-file-circle-check"></i> Documents <?= $verifiedDocs ?>/<?= count($docs) ?> verified</span>
            <span class="tag"><i class="fa-solid fa-user-shield"></i> Admin review <?= $c['admin_status']==='verified' ? 'Approved ✓' : ($c['admin_status']==='rejected' ? 'Rejected' : 'Pending') ?></span>
          </div>

          <div class="info-row"><span>Campaign is for</span><span class="val"><?= e($c['beneficiary_name'] ?: '—') ?></span></div>
          <div class="info-row"><span>Organiser's relation</span><span class="val"><?= e($c['beneficiary_relation'] ?: '—') ?></span></div>
          <div class="info-row"><span>Money is received via</span><span class="val"><?= e(receive_method_label($c['receive_method'])) ?></span></div>
          <div class="info-row"><span>Organiser NID</span><span class="val"><?= e(mask_nid($c['nid_number'])) ?></span></div>

          <h4 style="margin:16px 0 4px;font-size:13.5px;">Submitted documents</h4>
          <ul class="cred-list">
            <?php foreach ($docs as $d): ?>
              <li>
                <span><i class="fa-solid fa-file-lines" style="color:var(--orange);"></i> <?= e($docTypes[$d['doc_type']] ?? $d['doc_type']) ?>
                  <?php if ($isOrganizer && $d['file_path']): ?> · <a href="file.php?t=campdoc&id=<?= $d['id'] ?>" target="_blank" style="color:var(--orange);font-weight:600;">view</a><?php endif; ?>
                  <?php if ($isOrganizer && $d['status']==='rejected' && $d['admin_note']): ?><br><small style="color:#b91c1c;">Admin: <?= e($d['admin_note']) ?></small><?php endif; ?>
                </span>
                <?= doc_status_pill($d['status']) ?>
              </li>
            <?php endforeach; ?>
            <?php if (!$docs): ?><li style="color:var(--text-muted);">No supporting documents have been submitted yet.</li><?php endif; ?>
          </ul>
          <?php if (!$isOrganizer): ?><p style="font-size:11.5px;color:var(--text-muted);margin:10px 0 0;">The files themselves are private (medical &amp; ID documents) and are reviewed only by platform admins.</p><?php endif; ?>
        </div>

        <?php if ($isOrganizer): ?>
        <div class="card">
          <h3><i class="fa-solid fa-folder-plus"></i> Add more credentials</h3>
          <?php foreach ($docErrors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="add_docs" value="1">
            <?php include __DIR__ . '/includes/doc_rows.php'; ?>
            <button class="btn btn-primary" type="submit" style="margin-top:14px;"><i class="fa-solid fa-upload"></i> Upload documents</button>
          </form>
        </div>
        <?php endif; ?>

        <div class="card" id="ledger">
          <h3><i class="fa-solid fa-list"></i> Public Donation Ledger (Transparency)</h3>
          <table class="simple-table">
            <tr><th>Donor</th><th>Amount</th><th>Date</th><th>Txn ID</th></tr>
            <?php foreach (array_slice($donations,0,25) as $d): ?>
              <tr>
                <td><?= $d['anonymous'] ? 'Anonymous Donor' : e($d['full_name']) ?></td>
                <td><?= taka($d['amount']) ?></td>
                <td><?= date('M d, Y', strtotime($d['created_at'])) ?></td>
                <td style="color:var(--text-muted);"><?= e($d['txn_ref']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$donations): ?><tr><td colspan="4" style="color:var(--text-muted);">No donations yet — be the first!</td></tr><?php endif; ?>
          </table>
        </div>
      </div>

      <div class="detail-side">
        <div class="card">
          <div style="font-size:26px;font-weight:800;"><?= taka($c['raised_amount']) ?></div>
          <div style="font-size:13px;color:var(--text-muted);margin-bottom:14px;">raised of <?= taka($c['goal_amount']) ?></div>
          <div class="progress-bar"><div style="width:<?= $pct ?>%"></div></div>
          <div class="campaign-stats"><span><?= count($donations) ?> donors</span><span><?= $pct ?>% funded · <?= $c['days_left'] ?> days left</span></div>

          <?php if ($formError): ?><div class="alert alert-error"><?= e($formError) ?></div><?php endif; ?>
          <?php if ($c['admin_status'] === 'rejected'): ?>
            <div class="alert alert-error">This campaign was rejected by an administrator and is not accepting donations.</div>
          <?php else: ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="donate" value="1">
            <div class="toggle-group">
              <input type="radio" name="preset" value="500" id="p1"><label for="p1">৳500</label>
              <input type="radio" name="preset" value="1000" id="p2" checked><label for="p2">৳1,000</label>
              <input type="radio" name="preset" value="2500" id="p3"><label for="p3">৳2,500</label>
            </div>
            <div class="form-group"><input type="number" id="amt" name="amount" value="1000" min="50" placeholder="Custom amount"></div>
            <div class="form-group"><label style="display:flex;align-items:center;gap:8px;font-weight:500;"><input type="checkbox" name="anonymous" style="width:auto;"> Donate anonymously</label></div>
            <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-heart"></i> Donate Now</button>
          </form>
          <?php endif; ?>
          <p style="font-size:11px;color:var(--text-muted);text-align:center;margin-top:10px;">Processed securely via bKash / Nagad / Card gateway</p>
        </div>

        <div class="safety-box">
          <i class="fa-solid fa-shield-halved"></i>
          <h4>Verified & Transparent</h4>
          <p>Every campaign is ID-checked and admin-reviewed before publishing. All donations are logged on a public ledger so funds can never be misused unnoticed.</p>
        </div>

        <?php if (!$isOrganizer): ?>
        <a href="report.php?type=campaign&id=<?= $id ?>&user=<?= $c['organizer_uid'] ?>&category=fake_campaign" class="btn btn-outline btn-block btn-sm" style="color:#b91c1c;"><i class="fa-solid fa-flag"></i> Report this campaign</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>
