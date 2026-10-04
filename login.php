<?php
require_once __DIR__ . '/includes/functions.php';
if (is_logged_in()) redirect('home.php');

$error = isset($_GET['suspended']) ? 'Your account has been suspended by an administrator. Please contact support.' : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role='user'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['status'] === 'suspended') {
            $error = 'Your account has been suspended by an administrator. Please contact support.';
        } else {
            $_SESSION['user_id'] = $user['id'];
            redirect('home.php');
        }
    } else {
        $error = 'Invalid email or password.';
    }
}
$page_title = 'Login';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-card">
    <a href="index.php" class="logo-link">
      <div class="logo">UIU <span>Friends</span></div>
      <div class="logo-sub">Loan &amp; Crowdfunding</div>
    </a>
    <p class="sub">Log in to your student loan & crowdfunding account.</p>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <div class="alert alert-info">Demo login: <strong>nadia@bscee.uiu.ac.bd</strong> / password: <strong>password123</strong></div>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <div class="form-group">
        <label>University Email</label>
        <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Log In</button>
    </form>
    <p class="form-note">Don't have an account? <a href="register.php">Sign up</a></p>
    <p class="form-note"><a href="admin/login.php">Admin login →</a></p>
  </div>
</div>
</body>
</html>
