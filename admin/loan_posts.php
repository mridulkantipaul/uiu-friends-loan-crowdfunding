<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$admin = current_admin();
$active = 'loan_posts.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['post_id'] ?? 0);
    if (isset($_POST['approve'])) $pdo->prepare("UPDATE loan_posts SET admin_status='approved' WHERE id=?")->execute([$id]);
    if (isset($_POST['remove']))  $pdo->prepare("UPDATE loan_posts SET admin_status='removed', status='closed' WHERE id=?")->execute([$id]);
    redirect('loan_posts.php');
}

$posts = $pdo->query("SELECT lp.*, u.full_name, u.email, u.avatar,
    (SELECT COUNT(*) FROM loan_offers WHERE loan_post_id=lp.id) offer_count
    FROM loan_posts lp JOIN users u ON u.id=lp.user_id ORDER BY lp.created_at DESC")->fetchAll();

$page_title = 'Loan Posts';
$base = '../';
$use_admin_css = true;
include __DIR__ . '/../includes/header.php';
?>
<div class="admin-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar"><h1>Loan Posts</h1><div class="mini-user"><img src="<?= e($admin['avatar']) ?>"><span><?= e($admin['full_name']) ?></span></div></div>
    <div class="admin-content">
      <div class="admin-table-card">
        <div class="head"><h3><?= count($posts) ?> Loan Requests</h3></div>
        <table class="admin-table">
          <tr><th>Borrower</th><th>Amount</th><th>Purpose</th><th>Offers</th><th>Status</th><th>Moderation</th><th>Posted</th><th>Actions</th></tr>
          <?php foreach ($posts as $p): ?>
          <tr>
            <td><div class="mini-user"><img src="<?= e($p['avatar']) ?>"><div><div class="n"><?= e($p['full_name']) ?></div><div class="s"><?= e($p['email']) ?></div></div></div></td>
            <td><?= taka($p['amount']) ?></td>
            <td><?= e($p['purpose']) ?></td>
            <td><?= $p['offer_count'] ?></td>
            <td><span class="pill pill-gray"><?= ucfirst($p['status']) ?></span></td>
            <td><?php
              $m = ['approved'=>'pill-green','pending'=>'pill-orange','removed'=>'pill-red'][$p['admin_status']];
              echo "<span class=\"pill $m\">" . ucfirst($p['admin_status']) . "</span>";
            ?></td>
            <td><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
            <td class="table-actions">
              <?php if ($p['admin_status'] !== 'approved'): ?>
              <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="post_id" value="<?= $p['id'] ?>"><input type="hidden" name="approve" value="1"><button class="link-btn" type="submit">Approve</button></form>
              <?php endif; ?>
              <?php if ($p['admin_status'] !== 'removed'): ?>
              <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="post_id" value="<?= $p['id'] ?>"><input type="hidden" name="remove" value="1"><button class="link-btn danger" type="submit">Remove</button></form>
              <?php endif; ?>
              <a href="view_post.php?id=<?= $p['id'] ?>" class="link-btn">View</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$posts): ?><tr><td colspan="8" style="color:var(--text-muted);">No loan posts yet.</td></tr><?php endif; ?>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
