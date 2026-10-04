<?php
/**
 * Permission-checked file viewer for ADMINS (review NID photos, campaign documents, report evidence).
 *   admin/file.php?t=nid_front|nid_back&id=<user id>
 *   admin/file.php?t=campdoc&id=<doc id>
 *   admin/file.php?t=report&id=<report id>
 */
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$t = $_GET['t'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$rel = null;

if ($t === 'nid_front' || $t === 'nid_back') {
    $col = $t === 'nid_front' ? 'nid_front' : 'nid_back';
    $st = $pdo->prepare("SELECT $col FROM users WHERE id = ?");
    $st->execute([$id]);
    $rel = $st->fetchColumn() ?: null;
} elseif ($t === 'campdoc') {
    $st = $pdo->prepare("SELECT file_path FROM campaign_documents WHERE id = ?");
    $st->execute([$id]);
    $rel = $st->fetchColumn() ?: null;
} elseif ($t === 'report') {
    $st = $pdo->prepare("SELECT evidence_file FROM reports WHERE id = ?");
    $st->execute([$id]);
    $rel = $st->fetchColumn() ?: null;
}
stream_upload($rel);
