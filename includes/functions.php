<?php
/**
 * UIU Friends Loan & Crowdfunding — shared helper functions
 */
session_start();
require_once __DIR__ . '/../config/db.php';

/* ---------------------------------------------------------- *
 *  AUTH HELPERS
 * ---------------------------------------------------------- */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    // A suspended account is logged out immediately
    $u = current_user();
    if (!$u || $u['status'] === 'suspended') {
        unset($_SESSION['user_id']);
        header('Location: login.php?suspended=1');
        exit;
    }
}

function require_admin(): void {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function current_user(): ?array {
    global $pdo;
    if (!is_logged_in()) return null;
    static $cache = null;
    if ($cache !== null) return $cache;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cache = $stmt->fetch();
    return $cache;
}

function current_admin(): ?array {
    global $pdo;
    if (!isset($_SESSION['admin_id'])) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch() ?: null;
}

/* ---------------------------------------------------------- *
 *  GENERAL HELPERS
 * ---------------------------------------------------------- */
function e(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function taka(float $amount): string {
    return '৳' . number_format($amount, $amount == floor($amount) ? 0 : 2);
}

function time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 172800) return 'Yesterday';
    return floor($diff / 86400) . 'd ago';
}

function truncate_text(string $text, int $len = 160, string $suffix = '…'): string {
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . $suffix : $text;
    }
    return strlen($text) > $len ? substr($text, 0, $len) . $suffix : $text;
}

function gen_txn_ref(): string {
    return 'UIUF-TXN-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

function flash(string $key, ?string $msg = null) {
    if ($msg !== null) { $_SESSION['flash'][$key] = $msg; return; }
    if (!empty($_SESSION['flash'][$key])) {
        $m = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $m;
    }
    return null;
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        die('Invalid request (CSRF check failed). Please go back and try again.');
    }
}

/* ---------------------------------------------------------- *
 *  DOMAIN HELPERS
 * ---------------------------------------------------------- */
function user_trust_breakdown(array $user): array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) c, AVG(rating) avg_rating FROM reviews WHERE reviewee_id = ?");
    $stmt->execute([$user['id']]);
    $r = $stmt->fetch();
    return [
        'repayment_reliability' => min(99, 70 + ($user['trust_score'] * 6)),
        'response_time'         => 'Excellent',
        'community_reviews'     => $r['avg_rating'] ? round($r['avg_rating'], 1) : $user['trust_score'],
        'review_count'          => (int)$r['c'],
    ];
}

function verification_badge(array $user): string {
    return $user['uiu_id_verified'] ? '<span class="badge-verified"><i class="fa-solid fa-circle-check"></i> Verified</span>' : '<span class="badge-pending"><i class="fa-solid fa-clock"></i> Pending</span>';
}


/* ---------------------------------------------------------- *
 *  NID (National ID) HELPERS
 *  A verified NID is required to borrow, lend, or start a
 *  campaign — so every money-related action is tied to a
 *  real, identifiable person.
 * ---------------------------------------------------------- */
function nid_is_valid_format(string $nid): bool {
    // Bangladesh NID: 10 digits (smart card), 13 or 17 digits (older cards)
    return (bool)preg_match('/^(\d{10}|\d{13}|\d{17})$/', $nid);
}

function nid_verified(?array $user): bool {
    return $user && ($user['nid_status'] ?? '') === 'verified';
}

function mask_nid(?string $nid): string {
    if (!$nid) return '—';
    $len = strlen($nid);
    return str_repeat('*', max(0, $len - 4)) . substr($nid, -4);
}

function nid_status_label(string $status): string {
    return [
        'not_submitted' => 'Not submitted',
        'pending'       => 'Under review',
        'verified'      => 'Verified',
        'rejected'      => 'Rejected',
    ][$status] ?? $status;
}

function nid_status_pill(string $status): string {
    $cls = ['verified' => 'pill-green', 'pending' => 'pill-orange', 'rejected' => 'pill-red', 'not_submitted' => 'pill-gray'][$status] ?? 'pill-gray';
    return '<span class="pill ' . $cls . '">' . e(nid_status_label($status)) . '</span>';
}

/** Returns an error message when the user may NOT perform a money-related action, or null when allowed. */
function nid_gate(?array $user, string $action = 'do this'): ?string {
    if (nid_verified($user)) return null;
    $st = $user['nid_status'] ?? 'not_submitted';
    if ($st === 'pending')  return "Your NID is still under admin review. You can $action once it is verified.";
    if ($st === 'rejected') return "Your NID was rejected. Please re-submit correct NID details to $action.";
    return "NID verification is required to $action. Please submit your NID first.";
}

/* ---------------------------------------------------------- *
 *  SECURE FILE UPLOADS  (NID photos, campaign documents, evidence)
 *  Files live in /uploads (blocked from direct web access) and
 *  are streamed only through file.php / admin/file.php after a
 *  permission check.
 * ---------------------------------------------------------- */
function uploads_root(): string {
    return dirname(__DIR__) . '/uploads';
}

/** Turns $_FILES['x'] (single or multiple) into a list of single-file arrays. */
function normalize_files(?array $files): array {
    if (!$files || !isset($files['name'])) return [];
    if (!is_array($files['name'])) return [$files];
    $out = [];
    foreach ($files['name'] as $i => $n) {
        $out[$i] = ['name' => $n, 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i], 'size' => $files['size'][$i]];
    }
    return $out;
}

/** Returns an error message, or null when the uploaded file is acceptable. */
function validate_upload(array $f, int $maxBytes = 4194304): ?string {
    $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) return 'No file was selected.';
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) return 'The file is too large for the server. Please upload a smaller file (max 4 MB).';
    if ($err !== UPLOAD_ERR_OK) return 'Upload failed. Please try again.';
    if ($f['size'] > $maxBytes) return 'Each file must be 4 MB or smaller.';
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) return 'Only JPG, PNG or PDF files are allowed.';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($fi, $f['tmp_name']);
        finfo_close($fi);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) return 'The file content is not a valid JPG, PNG or PDF.';
    }
    return null;
}

/** Saves a validated upload under /uploads/<subdir>/ and returns its relative path (or null). */
function save_upload(array $f, string $subdir): ?string {
    if (validate_upload($f) !== null) return null;
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if ($ext === 'jpeg') $ext = 'jpg';
    $dir = uploads_root() . '/' . $subdir;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return null;
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) return null;
    return $subdir . '/' . $name;
}

function delete_upload(?string $rel): void {
    if (!$rel) return;
    $root = realpath(uploads_root());
    $path = realpath(uploads_root() . '/' . $rel);
    if ($root && $path && str_starts_with($path, $root . DIRECTORY_SEPARATOR) && is_file($path)) @unlink($path);
}

/** Streams a stored file to the browser (path traversal safe). */
function stream_upload(?string $rel): void {
    $root = realpath(uploads_root());
    $path = ($root && $rel) ? realpath($root . '/' . $rel) : false;
    if (!$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
        http_response_code(404);
        die('File not found. (Demo records have no uploaded file.)');
    }
    $mime = function_exists('mime_content_type') ? mime_content_type($path) : 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline; filename="' . basename($path) . '"');
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

/* ---------------------------------------------------------- *
 *  CAMPAIGN CREDENTIALS
 * ---------------------------------------------------------- */
function campaign_doc_types(): array {
    return [
        'medical_report'      => 'Medical report / diagnosis',
        'prescription'        => 'Doctor\'s prescription',
        'hospital_estimate'   => 'Hospital cost estimate / bill',
        'hospital_letter'     => 'Hospital / doctor\'s letter',
        'beneficiary_id'      => 'Beneficiary ID (NID / Student ID / Birth certificate)',
        'dept_recommendation' => 'Recommendation letter (department / faculty / advisor)',
        'fee_notice'          => 'Fee notice / admission document',
        'police_report'       => 'Police GD / accident report',
        'account_proof'       => 'Bank / bKash / Nagad account proof',
        'other'               => 'Other supporting document',
    ];
}

function campaign_categories(): array {
    return ['Medical', 'Education', 'Emergency', 'Accident', 'Community', 'Other'];
}

function receive_method_label(?string $m): string {
    return ['bkash' => 'bKash', 'nagad' => 'Nagad', 'bank' => 'Bank account', 'hospital' => 'Directly to hospital / institution', 'other' => 'Other'][$m ?? ''] ?? '—';
}

/** Validates the document rows of a form. Returns [list of ['type','file'], list of errors]. */
function collect_doc_uploads(array $post, ?array $filesArr): array {
    $types = $post['doc_type'] ?? [];
    $files = normalize_files($filesArr);
    $valid = campaign_doc_types();
    $list = []; $errors = [];
    foreach ($types as $i => $type) {
        $f = $files[$i] ?? null;
        $noFile = !$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE;
        if ($type === '' && $noFile) continue;                       // empty row
        if ($type === '' || !isset($valid[$type])) { $errors[] = 'Row ' . ($i + 1) . ': please choose a document type.'; continue; }
        if ($noFile) { $errors[] = 'Row ' . ($i + 1) . ': please choose a file for "' . $valid[$type] . '".'; continue; }
        if ($msg = validate_upload($f)) { $errors[] = 'Row ' . ($i + 1) . ': ' . $msg; continue; }
        $list[] = ['type' => $type, 'file' => $f];
    }
    return [$list, $errors];
}

function save_campaign_docs(PDO $pdo, int $campaignId, array $list): int {
    $n = 0;
    foreach ($list as $item) {
        $path = save_upload($item['file'], 'campaigns');
        if (!$path) continue;
        $pdo->prepare("INSERT INTO campaign_documents (campaign_id, doc_type, file_path, original_name) VALUES (?,?,?,?)")
            ->execute([$campaignId, $item['type'], $path, mb_substr(basename($item['file']['name']), 0, 200)]);
        $n++;
    }
    return $n;
}

function doc_status_pill(string $status): string {
    $cls = ['verified' => 'pill-green', 'pending' => 'pill-orange', 'rejected' => 'pill-red'][$status] ?? 'pill-gray';
    $lbl = ['verified' => 'Verified', 'pending' => 'Under review', 'rejected' => 'Rejected'][$status] ?? $status;
    return '<span class="pill ' . $cls . '">' . $lbl . '</span>';
}

/* ---------------------------------------------------------- *
 *  REPORTS / COMPLAINTS
 * ---------------------------------------------------------- */
function report_categories(): array {
    return [
        'fraud'          => 'Fraud / Scam',
        'not_repaying'   => 'Borrower not repaying / ran away with money',
        'unfair_lender'  => 'Lender demanding extra money / unfair terms',
        'fake_campaign'  => 'Fake or misleading campaign',
        'fake_profile'   => 'Fake profile / wrong NID or student ID',
        'abusive_chat'   => 'Abusive or threatening messages',
        'payment_issue'  => 'Payment problem',
        'other'          => 'Other issue',
    ];
}

function report_status_pill(string $status): string {
    $cls = ['open' => 'pill-red', 'reviewing' => 'pill-orange', 'resolved' => 'pill-green', 'dismissed' => 'pill-gray'][$status] ?? 'pill-gray';
    return '<span class="pill ' . $cls . '">' . ucfirst($status) . '</span>';
}
