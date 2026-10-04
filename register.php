<?php
require_once __DIR__ . '/includes/functions.php';
if (is_logged_in()) redirect('home.php');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name       = trim($_POST['full_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $dept       = trim($_POST['department'] ?? '');
    $batch      = trim($_POST['batch'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $nid        = preg_replace('/\s+/', '', trim($_POST['nid_number'] ?? ''));
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    if ($name === '' || $email === '' || $student_id === '' || $dept === '' || $batch === '' || $password === '') {
        $errors[] = 'Please fill in all required fields.';
    }
    if ($nid === '' || !nid_is_valid_format($nid)) {
        $errors[] = 'Please enter a valid National ID (NID) number — 10, 13 or 17 digits.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if (!str_ends_with(strtolower($email), 'uiu.ac.bd') && !empty($email) && !str_contains($email, '@')) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? OR student_id=?");
        $stmt->execute([$email, $student_id]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email or student ID already exists.';
        }
        // one NID = one account (stops the same person opening many accounts)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE nid_number=?");
        $stmt->execute([$nid]);
        if ($stmt->fetch()) {
            $errors[] = 'This NID is already registered with another account.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (full_name,email,password_hash,student_id,department,batch,phone,nid_number,role,trust_score) VALUES (?,?,?,?,?,?,?,?,'user',4.00)");
        $stmt->execute([$name, $email, $hash, $student_id, $dept, $batch, $phone, $nid]);
        $_SESSION['user_id'] = $pdo->lastInsertId();
        flash('success', 'Welcome to UIU Friends! Now upload photos of your NID so an admin can verify you — this is required before you can borrow, lend or start a campaign.');
        redirect('security.php#nid');
    }
}
$page_title = 'Create Account';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-card">
    <a href="index.php" class="logo-link">
      <div class="logo">UIU <span>Friends</span></div>
      <div class="logo-sub">Loan &amp; Crowdfunding</div>
    </a>
    <p class="sub">Create your account to borrow, lend, or start a campaign.</p>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>University Email</label>
        <input type="email" name="email" placeholder="you@bscse.uiu.ac.bd" value="<?= e($_POST['email'] ?? '') ?>" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Student ID</label>
          <input type="text" name="student_id" value="<?= e($_POST['student_id'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label>National ID (NID) Number</label>
        <input type="text" name="nid_number" inputmode="numeric" placeholder="10, 13 or 17 digit NID" value="<?= e($_POST['nid_number'] ?? '') ?>" required>
        <small style="color:var(--text-muted);font-size:11.5px;">Required for borrowing safety. You will upload your NID photos after signing up.</small>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Department</label>
          <select name="department" required>
            <option value="">Select</option>
            <?php foreach (['CSE','EEE','BBA','SLM','CE','Pharmacy','Economics'] as $d): ?>
              <option value="<?= $d ?>" <?= ($_POST['department'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Batch</label>
          <input type="text" name="batch" placeholder="2022" value="<?= e($_POST['batch'] ?? '') ?>" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" required>
        </div>
        <div class="form-group">
          <label>Confirm Password</label>
          <input type="password" name="confirm_password" required>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Create Account</button>
    </form>
    <p class="form-note">Already have an account? <a href="login.php">Log in</a></p>
  </div>
</div>
</body>
</html>
