<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$u = current_user();
$active = 'crowdfunding.php';

$formErrors = [];
$showForm = isset($_GET['new']) || $_SERVER['REQUEST_METHOD'] === 'POST';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_campaign'])) {
    csrf_check();
    $title  = trim($_POST['title'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $goal   = (float)($_POST['goal_amount'] ?? 0);
    $days   = max(1, (int)($_POST['days_left'] ?? 30));
    $cat    = in_array($_POST['category'] ?? '', campaign_categories(), true) ? $_POST['category'] : 'Other';
    $ben    = trim($_POST['beneficiary_name'] ?? '');
    $rel    = trim($_POST['beneficiary_relation'] ?? '');
    $phone  = trim($_POST['contact_phone'] ?? '');
    $method = in_array($_POST['receive_method'] ?? '', ['bkash','nagad','bank','hospital','other'], true) ? $_POST['receive_method'] : null;
    $acct   = trim($_POST['receive_account'] ?? '');

    if ($gate = nid_gate($u, 'start a campaign')) $formErrors[] = $gate;
    if (!$title || !$desc || $goal <= 0) $formErrors[] = 'Please fill in the title, description and goal amount.';
    if ($ben === '')  $formErrors[] = 'Please enter the name of the person / group this campaign is for.';
    if ($phone === '') $formErrors[] = 'Please enter a contact phone number so admins can verify the campaign.';
    if (!$method || $acct === '') $formErrors[] = 'Please tell us how the money will be received (account / hospital details).';

    [$docList, $docErrors] = collect_doc_uploads($_POST, $_FILES['doc_file'] ?? null);
    $formErrors = array_merge($formErrors, $docErrors);

    if (!$formErrors) {
        $stmt = $pdo->prepare("INSERT INTO campaigns (organizer_id, title, description, goal_amount, days_left, category,
                beneficiary_name, beneficiary_relation, contact_phone, receive_method, receive_account, admin_status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,'pending')");
        $stmt->execute([$u['id'], $title, $desc, $goal, $days, $cat, $ben, $rel, $phone, $method, $acct]);
        $cid = (int)$pdo->lastInsertId();
        $saved = save_campaign_docs($pdo, $cid, $docList);
        flash('success', 'Your campaign was submitted with ' . $saved . ' credential document' . ($saved === 1 ? '' : 's') . ' and is pending admin verification.');
        redirect('campaign_details.php?id=' . $cid);
    }
}

$q = trim($_GET['q'] ?? '');
$sql = "SELECT c.*, u.full_name, u.department, u.batch, u.uiu_id_verified,
        (SELECT COUNT(*) FROM campaign_documents d WHERE d.campaign_id=c.id) doc_count,
        (SELECT COUNT(*) FROM campaign_documents d WHERE d.campaign_id=c.id AND d.status='verified') doc_verified
        FROM campaigns c JOIN users u ON u.id = c.organizer_id";
$params = [];
if ($q !== '') {
    $sql .= " WHERE (c.title LIKE ? OR c.description LIKE ? OR u.full_name LIKE ?)";
    $like = "%$q%";
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$campaigns = $stmt->fetchAll();

$gate = nid_gate($u, 'start a campaign');
$page_title = 'Crowdfunding';
include __DIR__ . '/includes/header.php';
?>
<div class="app-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main-area">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <div class="content-wrap">
      <div class="content-main">
        <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

        <?php if ($showForm && $gate && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
        <div class="card">
          <h3><i class="fa-solid fa-address-card"></i> NID verification required</h3>
          <div class="alert alert-error"><?= e($gate) ?></div>
          <p style="font-size:13.5px;color:var(--text-muted);margin-top:0;">Campaign organisers must be identified by a verified National ID so donors can trust where their money goes.</p>
          <a href="security.php#nid" class="btn btn-primary"><i class="fa-solid fa-address-card"></i> Verify my NID</a>
        </div>
        <?php elseif ($showForm): ?>
        <div class="card">
          <h3><i class="fa-solid fa-bullseye"></i> Start a Crowdfunding Campaign</h3>
          <?php foreach ($formErrors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="create_campaign" value="1">

            <h4 style="margin:4px 0 12px;font-size:14px;">1. Campaign details</h4>
            <div class="form-row">
              <div class="form-group" style="flex:2;"><label>Campaign Title</label><input type="text" name="title" value="<?= e($_POST['title'] ?? '') ?>" required></div>
              <div class="form-group"><label>Category</label>
                <select name="category">
                  <?php foreach (campaign_categories() as $c): ?><option <?= ($_POST['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="form-group"><label>Full story / description</label><textarea name="description" rows="4" placeholder="What happened? Why is help needed? How will the money be used?" required><?= e($_POST['description'] ?? '') ?></textarea></div>
            <div class="form-row">
              <div class="form-group"><label>Goal Amount (৳)</label><input type="number" name="goal_amount" min="500" value="<?= e($_POST['goal_amount'] ?? '') ?>" required></div>
              <div class="form-group"><label>Duration (days)</label><input type="number" name="days_left" value="<?= e($_POST['days_left'] ?? 30) ?>" min="1"></div>
            </div>

            <h4 style="margin:18px 0 12px;font-size:14px;">2. Who is this for? (so admins can verify)</h4>
            <div class="form-row">
              <div class="form-group"><label>Beneficiary name</label><input type="text" name="beneficiary_name" value="<?= e($_POST['beneficiary_name'] ?? '') ?>" placeholder="Person / group who receives help" required></div>
              <div class="form-group"><label>Your relation to them</label><input type="text" name="beneficiary_relation" value="<?= e($_POST['beneficiary_relation'] ?? '') ?>" placeholder="e.g. Friend, Brother, Classmate"></div>
              <div class="form-group"><label>Contact phone</label><input type="text" name="contact_phone" value="<?= e($_POST['contact_phone'] ?? $u['phone']) ?>" required></div>
            </div>

            <h4 style="margin:18px 0 12px;font-size:14px;">3. How will the money be received?</h4>
            <div class="form-row">
              <div class="form-group"><label>Receiving method</label>
                <select name="receive_method" required>
                  <option value="">— Select —</option>
                  <?php foreach (['hospital','bkash','nagad','bank','other'] as $m): ?><option value="<?= $m ?>" <?= ($_POST['receive_method'] ?? '') === $m ? 'selected' : '' ?>><?= e(receive_method_label($m)) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="form-group" style="flex:2;"><label>Account / hospital details</label><input type="text" name="receive_account" value="<?= e($_POST['receive_account'] ?? '') ?>" placeholder="e.g. Square Hospital A/C 1234… or bKash 017XXXXXXXX" required></div>
            </div>

            <h4 style="margin:18px 0 6px;font-size:14px;">4. Credentials &amp; supporting documents</h4>
            <p style="font-size:12.5px;color:var(--text-muted);margin:0 0 12px;">Add every document that proves your campaign is genuine — medical reports, hospital estimates, prescriptions, the beneficiary's ID, a department recommendation letter, police GD, account proof, etc. Campaigns with more verified documents get approved faster and receive more donations.</p>
            <?php include __DIR__ . '/includes/doc_rows.php'; ?>

            <div class="alert alert-info" style="margin-top:16px;">Your NID (<?= e(mask_nid($u['nid_number'])) ?>) is already verified and is automatically attached to this campaign as the organiser's identity.</div>
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Submit Campaign for Review</button>
          </form>
        </div>
        <?php endif; ?>

        <div class="card">
          <form method="get" action="crowdfunding.php" style="display:flex;gap:10px;align-items:center;">
            <div class="search-box" style="flex:1;width:auto;">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search campaigns by title, description, or organizer...">
            </div>
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($q !== ''): ?><a href="crowdfunding.php" class="btn btn-outline">Clear</a><?php endif; ?>
          </form>
        </div>

        <div class="filter-tabs">
          <a href="crowdfunding.php" class="<?= (!$showForm && $q === '') ? 'active' : '' ?>">All Campaigns</a>
          <a href="crowdfunding.php?new=1" class="<?= $showForm ? 'active' : '' ?>">+ Start a Campaign</a>
        </div>

        <?php if ($q !== ''): ?>
          <p style="font-size:13.5px;color:var(--text-muted);"><strong><?= count($campaigns) ?></strong> campaign<?= count($campaigns)===1?'':'s' ?> matching "<?= e($q) ?>"</p>
        <?php endif; ?>

        <?php if (!$campaigns): ?>
          <div class="card empty-state"><i class="fa-regular fa-folder-open"></i>
            <p><?= $q !== '' ? 'No campaigns matched your search.' : 'No campaigns yet.' ?></p>
          </div>
        <?php endif; ?>

        <?php foreach ($campaigns as $c): $pct = min(100, round($c['raised_amount'] / max(1,$c['goal_amount']) * 100)); ?>
          <div class="card campaign-mini">
            <div class="top">
              <span><?= $c['admin_status']==='verified' ? '<span class="badge-verified"><i class="fa-solid fa-circle-check"></i> Verified Campaign</span>' : ($c['admin_status']==='rejected' ? '<span style="color:#b91c1c;">Rejected</span>' : '<span class="badge-pending"><i class="fa-solid fa-clock"></i> Pending Verification</span>') ?></span>
              <span><?= e($c['category']) ?> · <?= time_ago($c['created_at']) ?></span>
            </div>
            <h4><?= e($c['title']) ?></h4>
            <div class="organizer">Organized by <?= e($c['full_name']) ?> · <?= e($c['department']) ?> '<?= substr($c['batch'],-2) ?> · <i class="fa-solid fa-file-shield"></i> <?= $c['doc_verified'] ?>/<?= $c['doc_count'] ?> credentials verified</div>
            <p class="post-desc"><?= e(truncate_text($c['description'], 160)) ?></p>
            <div class="progress-bar"><div style="width:<?= $pct ?>%"></div></div>
            <div class="campaign-stats">
              <span><strong><?= taka($c['raised_amount']) ?></strong> raised of <?= taka($c['goal_amount']) ?></span>
              <span><?= $pct ?>% funded · <?= $c['days_left'] ?> days left</span>
            </div>
            <div class="post-actions">
              <a href="campaign_details.php?id=<?= $c['id'] ?>" class="btn btn-primary"><i class="fa-solid fa-heart"></i> View & Donate</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="content-side">
        <div class="safety-box">
          <i class="fa-solid fa-shield-halved"></i>
          <h4>Verified & Transparent</h4>
          <p>Every campaign is ID-checked and admin-reviewed before publishing. All donations are logged on a public ledger so funds can never be misused unnoticed.</p>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
