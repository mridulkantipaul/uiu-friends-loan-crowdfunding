<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$u = current_user();
$active = 'report.php';

$categories = report_categories();
$relTypes = ['loan_post' => 'Loan post', 'campaign' => 'Campaign', 'agreement' => 'Loan agreement', 'chat' => 'Chat conversation', 'user' => 'User profile', 'other' => 'Other'];

// Values can come pre-filled from a "Report" button on a post / campaign / deal / profile / chat
$preUser = (int)($_GET['user'] ?? $_POST['reported_user_id'] ?? 0);
$preType = $_GET['type'] ?? $_POST['related_type'] ?? 'other';
$preId   = (int)($_GET['id'] ?? $_POST['related_id'] ?? 0);
$preCat  = $_GET['category'] ?? $_POST['category'] ?? '';
if (!isset($relTypes[$preType])) $preType = 'other';
if ($preCat === '' && $preType === 'campaign') $preCat = 'fake_campaign';
if ($preCat === '' && $preType === 'chat') $preCat = 'abusive_chat';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    csrf_check();
    $category = $_POST['category'] ?? '';
    $subject  = trim($_POST['reason'] ?? '');
    $details  = trim($_POST['details'] ?? '');
    $against  = (int)($_POST['reported_user_id'] ?? 0);

    if (!isset($categories[$category])) $errors[] = 'Please choose what kind of issue this is.';
    if (mb_strlen($subject) < 5 || mb_strlen($subject) > 150) $errors[] = 'Please write a short subject (5–150 characters).';
    if (mb_strlen($details) < 20) $errors[] = 'Please describe the problem in detail (at least 20 characters) so admins can act on it.';
    if ($against === (int)$u['id']) $errors[] = 'You cannot report yourself.';
    if ($against) {
        $chk = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='user'");
        $chk->execute([$against]);
        if (!$chk->fetch()) $against = 0;
    }

    $evidence = null;
    $ev = $_FILES['evidence'] ?? null;
    $hasEv = $ev && ($ev['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($hasEv && ($msg = validate_upload($ev))) $errors[] = 'Evidence file: ' . $msg;

    if (!$errors) {
        if ($hasEv) $evidence = save_upload($ev, 'reports');
        $agreementId = ($preType === 'agreement' && $preId) ? $preId : null;
        $pdo->prepare("INSERT INTO reports (reporter_id, reported_user_id, category, reason, details, related_type, related_id, agreement_id, evidence_file)
                       VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$u['id'], $against ?: null, $category, $subject, $details, $preType, $preId ?: null, $agreementId, $evidence]);
        flash('success', 'Your report was sent to the administrators. You can follow its status below.');
        redirect('report.php');
    }
}

$users = $pdo->prepare("SELECT id, full_name, student_id, department FROM users WHERE role='user' AND id <> ? ORDER BY full_name");
$users->execute([$u['id']]);
$users = $users->fetchAll();

$mine = $pdo->prepare("SELECT r.*, ru.full_name reported_name FROM reports r LEFT JOIN users ru ON ru.id = r.reported_user_id
                       WHERE r.reporter_id = ? ORDER BY r.created_at DESC");
$mine->execute([$u['id']]);
$mine = $mine->fetchAll();

$showForm = $errors || isset($_GET['type']) || isset($_GET['user']) || isset($_GET['new']) || !$mine;

$page_title = 'Report an Issue';
include __DIR__ . '/includes/header.php';
?>
<div class="app-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main-area">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <div class="content-wrap">
      <div class="content-main">
        <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

        <?php if ($showForm): ?>
        <div class="card">
          <h3><i class="fa-solid fa-flag"></i> Report an Issue</h3>
          <p style="font-size:13px;color:var(--text-muted);margin-top:-6px;">Tell us about fraud, non-payment, fake campaigns or abuse. Every report goes straight to the platform administrators.</p>
          <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="submit_report" value="1">
            <input type="hidden" name="related_type" value="<?= e($preType) ?>">
            <input type="hidden" name="related_id" value="<?= $preId ?>">

            <?php if ($preType !== 'other'): ?>
              <div class="alert alert-info">Regarding: <strong><?= e($relTypes[$preType]) ?><?= $preId ? ' #' . $preId : '' ?></strong></div>
            <?php endif; ?>

            <div class="form-row">
              <div class="form-group">
                <label>What happened?</label>
                <select name="category" required>
                  <option value="">— Select issue type —</option>
                  <?php foreach ($categories as $k => $label): ?><option value="<?= $k ?>" <?= $preCat === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Who are you reporting? <small style="font-weight:400;color:var(--text-muted);">(if it involves a person)</small></label>
                <select name="reported_user_id">
                  <option value="0">— Not about a specific person —</option>
                  <?php foreach ($users as $r): ?><option value="<?= $r['id'] ?>" <?= $preUser === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['full_name']) ?> (<?= e($r['department']) ?> · <?= e($r['student_id']) ?>)</option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="form-group"><label>Short subject</label><input type="text" name="reason" maxlength="150" value="<?= e($_POST['reason'] ?? '') ?>" placeholder="e.g. Borrower stopped replying after receiving ৳15,000" required></div>
            <div class="form-group"><label>Full details</label><textarea name="details" rows="5" placeholder="What happened, when, and any amounts / transaction IDs involved..." required><?= e($_POST['details'] ?? '') ?></textarea></div>
            <div class="form-group"><label>Evidence (optional) — screenshot or PDF</label><input type="file" name="evidence" accept=".jpg,.jpeg,.png,.pdf"></div>
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Send Report to Admin</button>
          </form>
        </div>
        <?php endif; ?>

        <div class="card">
          <h3>My Reports (<?= count($mine) ?>)
            <?php if (!$showForm): ?><a href="report.php?new=1" class="btn btn-primary btn-sm" style="float:right;"><i class="fa-solid fa-plus"></i> New report</a><?php endif; ?>
          </h3>
          <?php if (!$mine): ?><p style="color:var(--text-muted);font-size:13.5px;">You have not filed any reports.</p><?php endif; ?>
          <?php foreach ($mine as $r): ?>
            <div class="review-item">
              <div class="who">
                <span><?= e($r['reason']) ?></span>
                <span><?= report_status_pill($r['status']) ?></span>
              </div>
              <div style="font-size:12px;color:var(--text-muted);margin:4px 0;">
                <?= e($categories[$r['category']] ?? $r['category']) ?>
                <?= $r['reported_name'] ? ' · against ' . e($r['reported_name']) : '' ?>
                · <?= date('M d, Y', strtotime($r['created_at'])) ?>
                <?php if ($r['evidence_file']): ?> · <a href="file.php?t=report&id=<?= $r['id'] ?>" target="_blank" style="color:var(--orange);">evidence</a><?php endif; ?>
              </div>
              <div class="comment"><?= nl2br(e(truncate_text((string)$r['details'], 300))) ?></div>
              <?php if ($r['admin_note']): ?>
                <div class="alert alert-info" style="margin:10px 0 0;"><strong>Admin response:</strong> <?= nl2br(e($r['admin_note'])) ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="content-side">
        <div class="safety-box">
          <i class="fa-solid fa-shield-halved"></i>
          <h4>We take reports seriously</h4>
          <p>Admins can review the chat history, agreements and payments linked to your report, verify NIDs and suspend accounts that break the rules.</p>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
