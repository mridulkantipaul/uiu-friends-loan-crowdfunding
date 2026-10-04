<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$admin = current_admin();
$active = 'users.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['user_id'] ?? 0);
    if (isset($_POST['toggle_verify'])) {
        $pdo->prepare("UPDATE users SET uiu_id_verified = 1 - uiu_id_verified WHERE id=? AND role='user'")->execute([$id]);
    } elseif (isset($_POST['toggle_status'])) {
        $pdo->prepare("UPDATE users SET status = IF(status='active','suspended','active') WHERE id=? AND role='user'")->execute([$id]);
    } elseif (isset($_POST['verify_all'])) {
        $pdo->exec("UPDATE users SET email_verified=1, phone_verified=1 WHERE role='user'");
    }
    redirect('users.php' . (isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
}

$q = trim($_GET['q'] ?? '');
$nidFilter = $_GET['nid'] ?? '';
$sql = "SELECT * FROM users WHERE role='user'";
$params = [];
if (in_array($nidFilter, ['pending','verified','rejected','not_submitted'], true)) { $sql .= " AND nid_status = ?"; $params[] = $nidFilter; }
if ($q !== '') { $sql .= " AND (full_name LIKE ? OR email LIKE ? OR student_id LIKE ?)"; $like = "%$q%"; array_push($params, $like, $like, $like); }
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$page_title = 'Manage Users';
$base = '../';
$use_admin_css = true;
include __DIR__ . '/../includes/header.php';
?>
<div class="admin-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1>Manage Users</h1>
      <form method="get" class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" name="q" value="<?= e($q) ?>" placeholder="Search students..."></form>
    </div>
    <div class="admin-content">
      <div class="admin-table-card">
        <div class="head"><h3><?= count($users) ?> Students</h3>
          <div class="table-actions">
            <a href="users.php" class="link-btn" style="<?= $nidFilter===''?'text-decoration:underline;':'' ?>">All</a>
            <a href="users.php?nid=pending" class="link-btn" style="<?= $nidFilter==='pending'?'text-decoration:underline;':'' ?>">NID pending</a>
            <a href="users.php?nid=rejected" class="link-btn" style="<?= $nidFilter==='rejected'?'text-decoration:underline;':'' ?>">NID rejected</a>
            <a href="users.php?nid=not_submitted" class="link-btn" style="<?= $nidFilter==='not_submitted'?'text-decoration:underline;':'' ?>">No NID</a>
            <a href="users.php?nid=verified" class="link-btn" style="<?= $nidFilter==='verified'?'text-decoration:underline;':'' ?>">NID verified</a>
          </div></div>
        <table class="admin-table">
          <tr><th>Student</th><th>Dept / Batch</th><th>Trust Score</th><th>NID</th><th>UIU ID</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
          <?php foreach ($users as $us): ?>
          <tr>
            <td><div class="mini-user"><img src="<?= e($us['avatar']) ?>"><div><div class="n"><?= e($us['full_name']) ?></div><div class="s"><?= e($us['email']) ?></div></div></div></td>
            <td><?= e($us['department']) ?> · '<?= substr($us['batch'],-2) ?></td>
            <td><i class="fa-solid fa-star" style="color:#f97316;"></i> <?= number_format($us['trust_score'],1) ?></td>
            <td><?= nid_status_pill($us['nid_status']) ?></td>
            <td><?= $us['uiu_id_verified'] ? '<span class="pill pill-green">Verified</span>' : '<span class="pill pill-orange">Pending</span>' ?></td>
            <td><?= $us['status']==='active' ? '<span class="pill pill-green">Active</span>' : '<span class="pill pill-red">Suspended</span>' ?></td>
            <td><?= date('M d, Y', strtotime($us['created_at'])) ?></td>
            <td class="table-actions">
              <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="user_id" value="<?= $us['id'] ?>"><input type="hidden" name="toggle_verify" value="1"><button class="link-btn" type="submit"><?= $us['uiu_id_verified']?'Unverify':'Verify ID' ?></button></form>
              <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="user_id" value="<?= $us['id'] ?>"><input type="hidden" name="toggle_status" value="1"><button class="link-btn danger" type="submit"><?= $us['status']==='active'?'Suspend':'Activate' ?></button></form>
              <a href="view_user.php?id=<?= $us['id'] ?>" class="link-btn">View Credentials</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$users): ?><tr><td colspan="8" style="color:var(--text-muted);">No students found.</td></tr><?php endif; ?>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
