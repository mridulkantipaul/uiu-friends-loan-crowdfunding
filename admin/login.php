<?php
require_once __DIR__ . '/../includes/functions.php';
if (isset($_SESSION['admin_id'])) redirect('dashboard.php');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? AND role='admin'");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = $admin['id'];
        redirect('dashboard.php');
    } else {
        $error = 'Invalid admin credentials.';
    }
}
$page_title = 'Admin Login';
$base = '../';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap" style="background:#0c0c11;">
  <div class="auth-card">
    <a href="../index.php" class="logo-link">
      <div class="logo">UIU <span>Friends</span></div>
      <div class="logo-sub">Loan &amp; Crowdfunding</div>
    </a>
    <p class="sub">Admin Panel — Loan & Crowdfunding Management</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <div class="alert alert-info">Demo admin: <strong>admin@uiufriends.com</strong> / password: <strong>password123</strong></div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <div class="form-group"><label>Admin Email</label><input type="email" name="email" required></div>
      <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
      <button type="submit" class="btn btn-dark btn-block"><i class="fa-solid fa-shield-halved"></i> Log In to Admin Panel</button>
    </form>
    <p class="form-note"><a href="../login.php">← Back to student login</a></p>
  </div>
</div>
</body>
</html>
