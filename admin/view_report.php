<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$admin = current_admin();
$active = 'reports.php';

$id = (int)($_GET['id'] ?? 0);
$cats = report_categories();

$stmt = $pdo->prepare("SELECT r.*, ru.full_name reporter_name, ru.email reporter_email, ru.avatar reporter_avatar, ru.phone reporter_phone,
        tu.id tu_id, tu.full_name reported_name, tu.email reported_email, tu.avatar reported_avatar, tu.status reported_status,
        tu.nid_status reported_nid_status, tu.nid_number reported_nid, tu.trust_score reported_trust, tu.phone reported_phone, tu.address reported_address
    FROM reports r JOIN users ru ON ru.id = r.reporter_id LEFT JOIN users tu ON tu.id = r.reported_user_id WHERE r.id = ?");
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r) { http_response_code(404); die('Report not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['update_report'])) {
        $newStatus = in_array($_POST['status'] ?? '', ['open','reviewing','resolved','dismissed'], true) ? $_POST['status'] : $r['status'];
        $note = trim($_POST['admin_note'] ?? '');
        $pdo->prepare("UPDATE reports SET status=?, admin_note=?, updated_at=NOW() WHERE id=?")->execute([$newStatus, $note ?: null, $id]);
        flash('success', 'Report updated. The reporter can now see your response.');
    } elseif (isset($_POST['toggle_suspend']) && $r['tu_id']) {
        $pdo->prepare("UPDATE users SET status = IF(status='active','suspended','active') WHERE id=? AND role='user'")->execute([$r['tu_id']]);
        flash('success', 'Account status of the reported user was changed.');
    } elseif (isset($_POST['mark_disputed']) && $r['related_type'] === 'agreement' && $r['related_id']) {
        $pdo->prepare("UPDATE agreements SET status='disputed' WHERE id=?")->execute([$r['related_id']]);
        flash('success', 'Agreement #' . $r['related_id'] . ' was marked as disputed.');
    }
    redirect('view_report.php?id=' . $id);
}

/* ---- context for the admin ---- */
$agreement = null;
if ($r['related_type'] === 'agreement' && $r['related_id']) {
    $s = $pdo->prepare("SELECT a.*, bu.full_name borrower_name, lu.full_name lender_name FROM agreements a
        JOIN users bu ON bu.id=a.borrower_id JOIN users lu ON lu.id=a.lender_id WHERE a.id=?");
    $s->execute([$r['related_id']]);
    $agreement = $s->fetch();
}
$chat = [];
if ($r['reported_user_id']) {
    $s = $pdo->prepare("SELECT m.*, u.full_name FROM messages m JOIN users u ON u.id = m.sender_id
        WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?) ORDER BY m.created_at DESC LIMIT 40");
    $s->execute([$r['reporter_id'], $r['reported_user_id'], $r['reported_user_id'], $r['reporter_id']]);
    $chat = array_reverse($s->fetchAll());
}
$otherReports = [];
if ($r['reported_user_id']) {
    $s = $pdo->prepare("SELECT id, reason, status, created_at FROM reports WHERE reported_user_id=? AND id<>? ORDER BY created_at DESC");
    $s->execute([$r['reported_user_id'], $id]);
    $otherReports = $s->fetchAll();
}

// link to the related item's admin page
$relLink = null;
if ($r['related_id']) {
    if ($r['related_type'] === 'loan_post') $relLink = 'view_post.php?id=' . $r['related_id'];
    if ($r['related_type'] === 'campaign')  $relLink = 'view_campaign.php?id=' . $r['related_id'];
}

$page_title = 'Report #' . $id;
$base = '../';
$use_admin_css = true;
include __DIR__ . '/../includes/header.php';
?>
<div class="admin-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1><a href="reports.php" style="color:var(--text-muted);"><i class="fa-solid fa-arrow-left"></i></a> &nbsp;Complaint #<?= $id ?></h1>
      <?= report_status_pill($r['status']) ?>
    </div>

    <div class="admin-content">
      <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

      <div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap;">
        <div style="flex:1;min-width:340px;display:flex;flex-direction:column;gap:20px;">

          <div class="admin-table-card">
            <div class="head"><h3><?= e($r['reason']) ?></h3><span class="pill pill-gray"><?= e($cats[$r['category']] ?? $r['category']) ?></span></div>
            <div style="padding:20px;">
              <p class="post-desc" style="font-size:14px;"><?= nl2br(e($r['details'] ?: '(no details provided)')) ?></p>
              <div class="info-row"><span>Filed on</span><span class="val"><?= date('M d, Y \a\t h:i A', strtotime($r['created_at'])) ?></span></div>
              <div class="info-row"><span>Related to</span><span class="val">
                <?= $r['related_type'] !== 'other' ? ucwords(str_replace('_',' ',$r['related_type'])) . ($r['related_id'] ? ' #' . $r['related_id'] : '') : '—' ?>
                <?php if ($relLink): ?> · <a href="<?= $relLink ?>" class="link-btn">Open →</a><?php endif; ?>
              </span></div>
              <?php if ($r['updated_at']): ?><div class="info-row"><span>Last updated</span><span class="val"><?= date('M d, Y h:i A', strtotime($r['updated_at'])) ?></span></div><?php endif; ?>
              <?php if ($r['evidence_file']): ?>
                <div style="margin-top:14px;">
                  <div style="font-size:12.5px;font-weight:700;margin-bottom:8px;">Evidence attached by the reporter</div>
                  <?php if (preg_match('/\.pdf$/i', $r['evidence_file'])): ?>
                    <a class="btn btn-outline btn-sm" href="file.php?t=report&id=<?= $id ?>" target="_blank"><i class="fa-solid fa-file-pdf"></i> Open PDF evidence</a>
                  <?php else: ?>
                    <a href="file.php?t=report&id=<?= $id ?>" target="_blank"><img class="file-thumb" src="file.php?t=report&id=<?= $id ?>" alt="Evidence"></a>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($agreement): ?>
          <div class="admin-table-card">
            <div class="head"><h3>Linked Agreement #<?= $agreement['id'] ?></h3>
              <span class="pill <?= $agreement['status']==='disputed'?'pill-red':($agreement['status']==='completed'?'pill-green':'pill-orange') ?>"><?= ucwords(str_replace('_',' ',$agreement['status'])) ?></span></div>
            <table class="admin-table">
              <tr><th>Borrower</th><th>Lender</th><th>Principal</th><th>Total payable</th><th>Tenure</th></tr>
              <tr><td><?= e($agreement['borrower_name']) ?></td><td><?= e($agreement['lender_name']) ?></td><td><?= taka($agreement['principal']) ?></td><td><?= taka($agreement['total_payable']) ?></td><td><?= $agreement['tenure_months'] ?> mo</td></tr>
            </table>
          </div>
          <?php endif; ?>

          <?php if ($chat): ?>
          <div class="admin-table-card">
            <div class="head"><h3>Chat between the two users (last <?= count($chat) ?> messages)</h3></div>
            <div style="padding:16px 20px;max-height:340px;overflow-y:auto;">
              <?php foreach ($chat as $m): ?>
                <div style="margin-bottom:10px;font-size:13px;">
                  <strong style="color:<?= $m['sender_id']==$r['reporter_id']?'var(--orange)':'#374151' ?>;"><?= e($m['full_name']) ?></strong>
                  <span style="color:var(--text-muted);font-size:11px;"> · <?= date('M d, h:i A', strtotime($m['created_at'])) ?></span><br>
                  <?= nl2br(e($m['body'])) ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Admin action form -->
          <div class="admin-table-card">
            <div class="head"><h3>Your decision</h3></div>
            <form method="post" style="padding:20px;">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="update_report" value="1">
              <div class="form-group">
                <label>Status</label>
                <select name="status">
                  <?php foreach (['open' => 'Open — not yet looked at', 'reviewing' => 'Under review — investigating', 'resolved' => 'Resolved — action taken', 'dismissed' => 'Dismissed — no violation found'] as $k => $lbl): ?>
                    <option value="<?= $k ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Response to the reporter <small style="font-weight:400;color:var(--text-muted);">(shown on their "Report an Issue" page)</small></label>
                <textarea name="admin_note" rows="3" placeholder="e.g. We contacted the borrower and suspended the account until the loan is repaid."><?= e($r['admin_note']) ?></textarea>
              </div>
              <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save decision</button>
            </form>
          </div>
        </div>

        <!-- Side column -->
        <div style="width:330px;flex-shrink:0;display:flex;flex-direction:column;gap:20px;">
          <div class="admin-table-card">
            <div class="head"><h3>Reported by</h3></div>
            <div style="padding:18px;">
              <div class="mini-user" style="margin-bottom:12px;"><img src="<?= e($r['reporter_avatar']) ?>" style="width:42px;height:42px;"><div><div class="n"><?= e($r['reporter_name']) ?></div><div class="s"><?= e($r['reporter_email']) ?></div></div></div>
              <div class="info-row"><span>Phone</span><span class="val"><?= e($r['reporter_phone'] ?: '—') ?></span></div>
              <a href="view_user.php?id=<?= $r['reporter_id'] ?>" class="btn btn-outline btn-block btn-sm" style="margin-top:12px;">View credentials</a>
            </div>
          </div>

          <div class="admin-table-card">
            <div class="head"><h3>Reported user</h3></div>
            <div style="padding:18px;">
              <?php if ($r['tu_id']): ?>
                <div class="mini-user" style="margin-bottom:12px;"><img src="<?= e($r['reported_avatar']) ?>" style="width:42px;height:42px;"><div><div class="n"><?= e($r['reported_name']) ?></div><div class="s"><?= e($r['reported_email']) ?></div></div></div>
                <div class="info-row"><span>Account</span><span class="val <?= $r['reported_status']==='active'?'green':'' ?>"><?= ucfirst($r['reported_status']) ?></span></div>
                <div class="info-row"><span>NID</span><span class="val"><?= e($r['reported_nid'] ?: '—') ?> <?= nid_status_pill($r['reported_nid_status']) ?></span></div>
                <div class="info-row"><span>Phone</span><span class="val"><?= e($r['reported_phone'] ?: '—') ?></span></div>
                <div class="info-row"><span>Address</span><span class="val" style="max-width:60%;text-align:right;"><?= e($r['reported_address'] ?: '—') ?></span></div>
                <div class="info-row"><span>Trust score</span><span class="val orange"><?= number_format($r['reported_trust'],1) ?> / 5</span></div>
                <a href="view_user.php?id=<?= $r['tu_id'] ?>" class="btn btn-outline btn-block btn-sm" style="margin-top:12px;"><i class="fa-solid fa-id-card"></i> View full credentials &amp; NID</a>
                <form method="post" style="margin-top:8px;">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="toggle_suspend" value="1">
                  <button class="btn btn-block btn-sm" type="submit" style="background:<?= $r['reported_status']==='active'?'#dc2626':'#16a34a' ?>;color:#fff;">
                    <i class="fa-solid fa-user-slash"></i> <?= $r['reported_status']==='active' ? 'Suspend this account' : 'Reactivate this account' ?>
                  </button>
                </form>
              <?php else: ?>
                <p style="font-size:13px;color:var(--text-muted);margin:0;">This report is not about a specific person.</p>
              <?php endif; ?>
              <?php if ($agreement && $agreement['status'] !== 'disputed'): ?>
                <form method="post" style="margin-top:8px;">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="mark_disputed" value="1">
                  <button class="btn btn-outline btn-block btn-sm" type="submit" style="color:#b91c1c;"><i class="fa-solid fa-gavel"></i> Mark agreement as disputed</button>
                </form>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($otherReports): ?>
          <div class="admin-table-card">
            <div class="head"><h3>Other reports against this user (<?= count($otherReports) ?>)</h3></div>
            <div style="padding:6px 18px 14px;">
              <?php foreach ($otherReports as $o): ?>
                <div class="info-row"><a href="view_report.php?id=<?= $o['id'] ?>" class="link-btn">#<?= $o['id'] ?> <?= e(truncate_text($o['reason'], 32)) ?></a><?= report_status_pill($o['status']) ?></div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
