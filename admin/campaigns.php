<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$admin = current_admin();
$active = 'campaigns.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['campaign_id'] ?? 0);
    if (isset($_POST['verify'])) $pdo->prepare("UPDATE campaigns SET admin_status='verified', id_verified=1, docs_verified=1 WHERE id=?")->execute([$id]);
    if (isset($_POST['reject']))  $pdo->prepare("UPDATE campaigns SET admin_status='rejected' WHERE id=?")->execute([$id]);
    redirect('campaigns.php');
}

$camps = $pdo->query("SELECT c.*, u.full_name, u.email, u.avatar,
    (SELECT COUNT(*) FROM donations WHERE campaign_id=c.id) donor_count,
    (SELECT COUNT(*) FROM campaign_documents WHERE campaign_id=c.id) doc_count,
    (SELECT COUNT(*) FROM campaign_documents WHERE campaign_id=c.id AND status='verified') doc_ok
    FROM campaigns c JOIN users u ON u.id=c.organizer_id ORDER BY c.created_at DESC")->fetchAll();

$page_title = 'Campaigns';
$base = '../';
$use_admin_css = true;
include __DIR__ . '/../includes/header.php';
?>
<div class="admin-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar"><h1>Crowdfunding Campaigns</h1><div class="mini-user"><img src="<?= e($admin['avatar']) ?>"><span><?= e($admin['full_name']) ?></span></div></div>
    <div class="admin-content">
      <div class="admin-table-card">
        <div class="head"><h3><?= count($camps) ?> Campaigns</h3></div>
        <table class="admin-table">
          <tr><th>Organizer</th><th>Title</th><th>Raised / Goal</th><th>Donors</th><th>Documents</th><th>Status</th><th>Posted</th><th>Actions</th></tr>
          <?php foreach ($camps as $c): ?>
          <tr>
            <td><div class="mini-user"><img src="<?= e($c['avatar']) ?>"><div><div class="n"><?= e($c['full_name']) ?></div><div class="s"><?= e($c['email']) ?></div></div></div></td>
            <td><?= e($c['title']) ?></td>
            <td><?= taka($c['raised_amount']) ?> / <?= taka($c['goal_amount']) ?></td>
            <td><?= $c['donor_count'] ?></td>
            <td><span class="pill <?= ($c['doc_count'] && $c['doc_ok']==$c['doc_count']) ? 'pill-green' : 'pill-orange' ?>"><?= $c['doc_ok'] ?>/<?= $c['doc_count'] ?> verified</span></td>
            <td><?php
              $m = ['verified'=>'pill-green','pending'=>'pill-orange','rejected'=>'pill-red'][$c['admin_status']];
              echo "<span class=\"pill $m\">" . ucfirst($c['admin_status']) . "</span>";
            ?></td>
            <td><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
            <td class="table-actions">
              <?php if ($c['admin_status'] !== 'verified'): ?>
              <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="campaign_id" value="<?= $c['id'] ?>"><input type="hidden" name="verify" value="1"><button class="link-btn" type="submit">Verify</button></form>
              <?php endif; ?>
              <?php if ($c['admin_status'] !== 'rejected'): ?>
              <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="campaign_id" value="<?= $c['id'] ?>"><input type="hidden" name="reject" value="1"><button class="link-btn danger" type="submit">Reject</button></form>
              <?php endif; ?>
              <a href="view_campaign.php?id=<?= $c['id'] ?>" class="link-btn">View</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$camps): ?><tr><td colspan="8" style="color:var(--text-muted);">No campaigns yet.</td></tr><?php endif; ?>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
