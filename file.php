<?php
/**
 * Permission-checked file viewer for STUDENTS.
 *   file.php?t=nid_front | nid_back          -> your own NID photos
 *   file.php?t=campdoc&id=<doc id>           -> a document of a campaign YOU organise
 *   file.php?t=report&id=<report id>         -> evidence of a report YOU filed
 */
require_once __DIR__ . '/includes/functions.php';
require_login();
$u = current_user();
$t = $_GET['t'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$rel = null;

if ($t === 'nid_front') {
    $rel = $u['nid_front'];
} elseif ($t === 'nid_back') {
    $rel = $u['nid_back'];
} elseif ($t === 'campdoc') {
    $st = $pdo->prepare("SELECT d.file_path FROM campaign_documents d JOIN campaigns c ON c.id = d.campaign_id WHERE d.id = ? AND c.organizer_id = ?");
    $st->execute([$id, $u['id']]);
    $rel = $st->fetchColumn() ?: null;
} elseif ($t === 'report') {
    $st = $pdo->prepare("SELECT evidence_file FROM reports WHERE id = ? AND reporter_id = ?");
    $st->execute([$id, $u['id']]);
    $rel = $st->fetchColumn() ?: null;
}
stream_upload($rel);
