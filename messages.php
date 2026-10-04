<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$u = current_user();
$active = 'messages.php';

// send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {
    csrf_check();
    $to = (int)$_POST['to'];
    $body = trim($_POST['body'] ?? '');
    $postId = !empty($_POST['post_id']) ? (int)$_POST['post_id'] : null;
    if ($body !== '' && $to > 0) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, loan_post_id, body) VALUES (?,?,?,?)");
        $stmt->execute([$u['id'], $to, $postId, $body]);
    }
    redirect('messages.php?to=' . $to . ($postId ? '&post=' . $postId : ''));
}

// Conversation partner list: everyone this user has exchanged messages with
$convStmt = $pdo->prepare("
    SELECT other_id, MAX(created_at) last_time FROM (
        SELECT CASE WHEN sender_id=? THEN receiver_id ELSE sender_id END AS other_id, created_at
        FROM messages WHERE sender_id=? OR receiver_id=?
    ) t GROUP BY other_id ORDER BY last_time DESC");
$convStmt->execute([$u['id'], $u['id'], $u['id']]);
$convs = $convStmt->fetchAll();

$activeTo = (int)($_GET['to'] ?? ($convs[0]['other_id'] ?? 0));
$activePostId = $_GET['post'] ?? null;

// mark read
if ($activeTo) {
    $pdo->prepare("UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=?")->execute([$activeTo, $u['id']]);
}

$partner = null;
if ($activeTo) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$activeTo]);
    $partner = $stmt->fetch();
}

$thread = [];
if ($activeTo) {
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY created_at ASC");
    $stmt->execute([$u['id'], $activeTo, $activeTo, $u['id']]);
    $thread = $stmt->fetchAll();
}

function conv_preview($pdo, $me, $other) {
    $stmt = $pdo->prepare("SELECT body FROM messages WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$me,$other,$other,$me]);
    $r = $stmt->fetch();
    return $r ? $r['body'] : '';
}

$page_title = 'Messages';
include __DIR__ . '/includes/header.php';
?>
<div class="app-shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main-area">
    <div class="messages-shell">
      <div class="conv-list">
        <div class="conv-list-head"><h2>Messages</h2>
          <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" placeholder="Search conversations..."></div>
        </div>
        <div style="overflow-y:auto;">
        <?php if (!$convs): ?>
          <div class="empty-state"><i class="fa-regular fa-comment"></i><p>No conversations yet. Message someone from a loan post!</p></div>
        <?php endif; ?>
        <?php foreach ($convs as $cv):
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?"); $stmt->execute([$cv['other_id']]); $ou = $stmt->fetch();
            if (!$ou) continue;
        ?>
          <a href="messages.php?to=<?= $ou['id'] ?>" class="conv-item <?= $ou['id']==$activeTo?'active':'' ?>">
            <img src="<?= e($ou['avatar']) ?>" alt="">
            <div style="flex:1;min-width:0;">
              <div class="name"><span><?= e($ou['full_name']) ?></span><span style="font-weight:400;color:var(--text-muted);font-size:11px;"><?= time_ago($cv['last_time']) ?></span></div>
              <div class="preview"><?= e(conv_preview($pdo, $u['id'], $ou['id'])) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
        </div>
      </div>

      <div class="chat-area">
        <?php if ($partner): ?>
        <div class="chat-head">
          <img src="<?= e($partner['avatar']) ?>" alt="">
          <div>
            <div style="font-weight:700;font-size:14px;"><?= e($partner['full_name']) ?> <?= $partner['uiu_id_verified'] ? '<span class="badge-verified"><i class="fa-solid fa-circle-check"></i> Verified</span>' : '' ?></div>
            <div style="font-size:12px;color:var(--text-muted);"><?= e($partner['department']) ?> · Batch <?= e($partner['batch']) ?></div>
          </div>
          <?php if ($activePostId): ?><a href="loan_details.php?id=<?= (int)$activePostId ?>" class="btn btn-outline btn-sm" style="margin-left:auto;">View Post</a><?php endif; ?>
        </div>
        <div class="chat-warning"><i class="fa-solid fa-triangle-exclamation"></i> This conversation is monitored for safety. Never share OTP or bank passwords in chat.</div>
        <div class="chat-body">
          <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
          <?php foreach ($thread as $m): ?>
            <div class="bubble <?= $m['sender_id']==$u['id']?'out':'in' ?>"><?= nl2br(e($m['body'])) ?></div>
          <?php endforeach; ?>
          <?php if (!$thread): ?><p style="text-align:center;color:var(--text-muted);font-size:13px;">Say hello to start the conversation.</p><?php endif; ?>
        </div>
        <form class="chat-input" method="post">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="send" value="1">
          <input type="hidden" name="to" value="<?= $partner['id'] ?>">
          <input type="hidden" name="post_id" value="<?= e($activePostId ?? '') ?>">
          <input type="text" name="body" placeholder="Type your message..." required autocomplete="off">
          <button type="submit"><i class="fa-solid fa-paper-plane"></i></button>
        </form>
        <div style="padding:0 24px 14px;">
          <a href="report.php?type=chat&user=<?= $partner['id'] ?>&category=abusive_chat" class="link-btn danger" style="font-size:11.5px;"><i class="fa-solid fa-flag"></i> Report this conversation</a>
        </div>
        <?php else: ?>
          <div class="empty-state" style="margin:auto;"><i class="fa-regular fa-comments"></i><p>Select a conversation to start chatting.</p></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>
