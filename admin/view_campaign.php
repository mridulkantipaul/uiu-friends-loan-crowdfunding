<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$admin = current_admin();
$active = 'campaigns.php';

$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['verify'])) {
        $pdo->prepare("UPDATE campaigns SET admin_status='verified', id_verified=1, docs_verified=1 WHERE id=?")->execute([$id]);
        flash('success', 'Campaign verified — it now shows the "Verified Campaign" badge.');
    }
    if (isset($_POST['reject'])) {
        $note = trim($_POST['note'] ?? '');
        $pdo->prepare("UPDATE campaigns SET admin_status='rejected', admin_note=? WHERE id=?")->execute([$note ?: null, $id]);
        flash('success', 'Campaign rejected.');
    }
    if (isset($_POST['doc_action'])) {
        $docId = (int)($_POST['doc_id'] ?? 0);
        $act = $_POST['doc_action'];
        $note = trim($_POST['doc_note'] ?? '');
        if (in_array($act, ['verified', 'rejected', 'pending'], true)) {
            $pdo->prepare("UPDATE campaign_documents SET status=?, admin_note=? WHERE id=? AND campaign_id=?")
                ->execute([$act, $note ?: null, $docId, $id]);
            // keep the campaign-level flag in sync: true when every document is verified
            $st = $pdo->prepare("SELECT COUNT(*) total, SUM(status='verified') ok FROM campaign_documents WHERE campaign_id=?");
            $st->execute([$id]);
            $t = $st->fetch();
            $all = ($t['total'] > 0 && (int)$t['ok'] === (int)$t['total']) ? 1 : 0;
            $pdo->prepare("UPDATE campaigns SET docs_verified=? WHERE id=?")->execute([$all, $id]);
            flash('success', 'Document marked as ' . $act . '.');
        }
    }
    redirect('view_campaign.php?id=' . $id);
}

$stmt = $pdo->prepare("SELECT c.*, u.id uid, u.full_name, u.email, u.student_id, u.department, u.batch,
        u.phone, u.avatar, u.trust_score, u.uiu_id_verified, u.status user_status, u.created_at joined,
        u.nid_status, u.nid_number, u.address
    FROM campaigns c JOIN users u ON u.id = c.organizer_id WHERE c.id = ?");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) { http_response_code(404); die('Campaign not found.'); }

$don = $pdo->prepare("SELECT d.*, u.full_name, u.email FROM donations d JOIN users u ON u.id=d.donor_id
    WHERE d.campaign_id=? ORDER BY d.created_at DESC");
$don->execute([$id]);
$donations = $don->fetchAll();

$ds = $pdo->prepare("SELECT * FROM campaign_documents WHERE campaign_id=? ORDER BY uploaded_at DESC, id DESC");
$ds->execute([$id]);
$docs = $ds->fetchAll();
$docTypes = campaign_doc_types();
$docsOk = count(array_filter($docs, fn($d) => $d['status'] === 'verified'));

$pct = min(100, round($c['raised_amount'] / max(1, $c['goal_amount']) * 100));

$page_title = 'Campaign #' . $id;
$base = '../';
$use_admin_css = true;
include __DIR__ . '/../includes/header.php';
?>
<div class="admin-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1><a href="campaigns.php" style="color:var(--text-muted);"><i class="fa-solid fa-arrow-left"></i></a> &nbsp;Campaign #<?= $id ?></h1>
      <div class="table-actions">
        <?php if ($c['admin_status'] !== 'verified'): ?>
          <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="verify" value="1"><button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-circle-check"></i> Verify Campaign</button></form>
        <?php endif; ?>
        <?php if ($c['admin_status'] !== 'rejected'): ?>
          <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="reject" value="1"><button class="btn btn-outline btn-sm" type="submit" style="color:#dc2626;"><i class="fa-solid fa-ban"></i> Reject</button></form>
        <?php endif; ?>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

      <?php if ($c['nid_status'] !== 'verified'): ?>
        <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> The organiser's NID is <strong><?= e(nid_status_label($c['nid_status'])) ?></strong>. Review it before verifying this campaign —
          <a href="view_user.php?id=<?= $c['uid'] ?>#nid" style="text-decoration:underline;font-weight:700;">open NID review</a>.</div>
      <?php endif; ?>

      <div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap;">
        <div style="flex:1;min-width:340px;display:flex;flex-direction:column;gap:20px;">

          <div class="admin-table-card">
            <div class="head"><h3>Campaign Content (as students see it)</h3>
              <?php $m = ['verified'=>'pill-green','pending'=>'pill-orange','rejected'=>'pill-red'][$c['admin_status']];
                echo "<span class=\"pill $m\">" . ucfirst($c['admin_status']) . "</span>"; ?>
            </div>
            <?php if ($c['image']): ?><img src="<?= e($c['image']) ?>" alt="" style="width:100%;height:220px;object-fit:cover;"><?php endif; ?>
            <div style="padding:20px;">
              <span class="tag"><?= e($c['category']) ?></span>
              <h2 style="margin:10px 0 10px;font-size:19px;"><?= e($c['title']) ?></h2>
              <p class="post-desc"><?= nl2br(e($c['description'])) ?></p>
              <div class="progress-bar"><div style="width:<?= $pct ?>%"></div></div>
              <div class="campaign-stats">
                <span><strong><?= taka($c['raised_amount']) ?></strong> raised of <?= taka($c['goal_amount']) ?></span>
                <span><?= $pct ?>% funded · <?= count($donations) ?> donors · <?= $c['days_left'] ?> days left</span>
              </div>
              <p style="font-size:12px;color:var(--text-muted);margin:0;">Created <?= date('M d, Y \a\t h:i A', strtotime($c['created_at'])) ?></p>
              <?php if ($c['admin_note']): ?><div class="alert alert-error" style="margin-top:12px;">Rejection note: <?= e($c['admin_note']) ?></div><?php endif; ?>
            </div>
          </div>

          <!-- ===== Credentials the organiser submitted ===== -->
          <div class="admin-table-card">
            <div class="head"><h3><i class="fa-solid fa-file-shield"></i> Credentials submitted by organiser</h3>
              <span class="pill <?= ($docsOk === count($docs) && $docs) ? 'pill-green' : 'pill-orange' ?>"><?= $docsOk ?>/<?= count($docs) ?> documents verified</span></div>
            <div style="padding:18px 20px 6px;">
              <div class="info-row"><span>Campaign is for</span><span class="val"><?= e($c['beneficiary_name'] ?: '—') ?></span></div>
              <div class="info-row"><span>Organiser's relation</span><span class="val"><?= e($c['beneficiary_relation'] ?: '—') ?></span></div>
              <div class="info-row"><span>Contact phone</span><span class="val"><?= e($c['contact_phone'] ?: '—') ?></span></div>
              <div class="info-row"><span>Money received via</span><span class="val"><?= e(receive_method_label($c['receive_method'])) ?></span></div>
              <div class="info-row"><span>Account / hospital details</span><span class="val" style="max-width:60%;text-align:right;"><?= e($c['receive_account'] ?: '—') ?></span></div>
            </div>
            <table class="admin-table">
              <tr><th>Document</th><th>File</th><th>Status</th><th>Decision</th></tr>
              <?php foreach ($docs as $d): ?>
              <tr>
                <td><strong><?= e($docTypes[$d['doc_type']] ?? $d['doc_type']) ?></strong>
                  <div style="font-size:11.5px;color:var(--text-muted);"><?= e($d['original_name']) ?> · <?= date('M d', strtotime($d['uploaded_at'])) ?></div>
                  <?php if ($d['admin_note']): ?><div style="font-size:11.5px;color:#b91c1c;">Note: <?= e($d['admin_note']) ?></div><?php endif; ?></td>
                <td>
                  <?php if ($d['file_path']): ?>
                    <?php if (preg_match('/\.pdf$/i', $d['file_path'])): ?>
                      <a class="link-btn" href="file.php?t=campdoc&id=<?= $d['id'] ?>" target="_blank"><i class="fa-solid fa-file-pdf"></i> Open PDF</a>
                    <?php else: ?>
                      <a href="file.php?t=campdoc&id=<?= $d['id'] ?>" target="_blank"><img src="file.php?t=campdoc&id=<?= $d['id'] ?>" alt="" style="width:70px;height:50px;object-fit:cover;border-radius:8px;border:1px solid var(--border);"></a>
                    <?php endif; ?>
                  <?php else: ?><span style="font-size:11.5px;color:var(--text-muted);">demo record<br>(no file)</span><?php endif; ?>
                </td>
                <td><?= doc_status_pill($d['status']) ?></td>
                <td>
                  <form method="post" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="doc_id" value="<?= $d['id'] ?>">
                    <input type="text" name="doc_note" placeholder="note (optional)" style="width:120px;border:1px solid var(--border);border-radius:8px;padding:6px 8px;font-size:12px;">
                    <button class="link-btn" name="doc_action" value="verified" type="submit">Verify</button>
                    <button class="link-btn danger" name="doc_action" value="rejected" type="submit">Reject</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$docs): ?><tr><td colspan="4" style="color:var(--text-muted);">The organiser has not uploaded any supporting documents.</td></tr><?php endif; ?>
            </table>
          </div>

          <div class="admin-table-card">
            <div class="head"><h3>Donation Ledger (<?= count($donations) ?>)</h3>
              <span style="font-size:12.5px;color:var(--text-muted);">Total: <strong><?= taka($c['raised_amount']) ?></strong></span></div>
            <table class="admin-table">
              <tr><th>Donor</th><th>Public Name</th><th>Amount</th><th>Txn Ref</th><th>Date</th></tr>
              <?php foreach ($donations as $d): ?>
              <tr>
                <td><div class="n"><?= e($d['full_name']) ?></div><div class="s" style="font-size:11.5px;color:var(--text-muted);"><?= e($d['email']) ?></div></td>
                <td><?= $d['anonymous'] ? '<span class="pill pill-gray">Anonymous</span>' : e($d['full_name']) ?></td>
                <td><?= taka($d['amount']) ?></td>
                <td style="color:var(--text-muted);"><?= e($d['txn_ref']) ?></td>
                <td><?= date('M d, Y h:i A', strtotime($d['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$donations): ?><tr><td colspan="5" style="color:var(--text-muted);">No donations yet.</td></tr><?php endif; ?>
            </table>
          </div>
        </div>

        <div style="width:320px;flex-shrink:0;display:flex;flex-direction:column;gap:20px;">
          <div class="admin-table-card">
            <div class="head"><h3>Organized By</h3></div>
            <div style="padding:18px;">
              <div class="mini-user" style="margin-bottom:14px;">
                <img src="<?= e($c['avatar']) ?>" style="width:46px;height:46px;">
                <div><div class="n" style="font-size:14px;"><?= e($c['full_name']) ?></div><div class="s"><?= e($c['email']) ?></div></div>
              </div>
              <div class="info-row"><span>NID</span><span class="val"><?= e($c['nid_number'] ?: '—') ?></span></div>
              <div class="info-row"><span>NID status</span><span class="val"><?= nid_status_pill($c['nid_status']) ?></span></div>
              <div class="info-row"><span>Student ID</span><span class="val"><?= e($c['student_id']) ?></span></div>
              <div class="info-row"><span>Department</span><span class="val"><?= e($c['department']) ?> · '<?= substr($c['batch'],-2) ?></span></div>
              <div class="info-row"><span>Phone</span><span class="val"><?= e($c['phone'] ?: '—') ?></span></div>
              <div class="info-row"><span>Address</span><span class="val" style="max-width:60%;text-align:right;"><?= e($c['address'] ?: '—') ?></span></div>
              <div class="info-row"><span>Trust Score</span><span class="val orange"><?= number_format($c['trust_score'],1) ?> / 5</span></div>
              <div class="info-row"><span>UIU ID</span><span class="val <?= $c['uiu_id_verified']?'green':'' ?>"><?= $c['uiu_id_verified'] ? 'Verified' : 'Not verified' ?></span></div>
              <div class="info-row"><span>Account</span><span class="val"><?= ucfirst($c['user_status']) ?></span></div>
              <a href="view_user.php?id=<?= $c['uid'] ?>" class="btn btn-primary btn-block btn-sm" style="margin-top:14px;">
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
