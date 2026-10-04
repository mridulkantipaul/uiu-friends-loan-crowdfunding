<?php
/** Expects $u (current user array) and $active (string) to be set */
$nav = [
    'home.php'          => ['icon' => 'fa-house', 'label' => 'Home'],
    'loan_posts.php'     => ['icon' => 'fa-hand-holding-dollar', 'label' => 'Loan Posts'],
    'crowdfunding.php'   => ['icon' => 'fa-bullseye', 'label' => 'Crowdfunding'],
    'messages.php'       => ['icon' => 'fa-comment-dots', 'label' => 'Messages'],
    'wallet.php'         => ['icon' => 'fa-wallet', 'label' => 'My Wallet'],
    'profile.php'        => ['icon' => 'fa-user', 'label' => 'My Profile'],
    'security.php'       => ['icon' => 'fa-shield-halved', 'label' => 'Security & Verification'],
    'report.php'         => ['icon' => 'fa-flag', 'label' => 'Report an Issue'],
];
global $pdo;
$unread = 0;
if (!empty($u)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) c FROM messages WHERE receiver_id=? AND is_read=0");
    $stmt->execute([$u['id']]);
    $unread = (int)$stmt->fetch()['c'];
}
?>
<aside class="sidebar">
  <a href="home.php" class="brand">
    <div class="brand-mark"><i class="fa-solid fa-hand-holding-heart"></i></div>
    <div class="brand-text">
      <div class="brand-name">UIU <span>Friends</span></div>
      <div class="brand-sub">Loan &amp; Crowdfunding</div>
    </div>
  </a>
  <nav class="sidebar-nav">
    <?php foreach ($nav as $file => $item): ?>
      <a href="<?= $file ?>" class="<?= $active === $file ? 'active' : '' ?>">
        <i class="fa-solid <?= $item['icon'] ?>"></i>
        <?= $item['label'] ?>
        <?php if ($file === 'messages.php' && $unread > 0): ?><span class="nav-badge"><?= $unread ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-trust">
    <strong><i class="fa-solid fa-star"></i> Trust Score</strong>
    Keep your profile updated to earn a higher trust score.
  </div>
  <a href="logout.php" class="sidebar-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</aside>
