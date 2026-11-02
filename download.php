<?php
/**
 * download.php
 * Serves a transcription or summary as a downloadable TXT file.
 *
 * GET parameters:
 *   - id:       summary record ID
 *   - type:     "transcription" or "summary"
 */

require_once __DIR__ . '/includes/auth.php';
auth_guard();

$uid  = current_user_id();
$id   = isset($_GET['id'])   ? (int)$_GET['id']   : 0;
$type = isset($_GET['type']) ? $_GET['type']       : 'summary';

if ($id <= 0 || !in_array($type, ['transcription', 'summary'], true)) {
    http_response_code(400);
    exit('Invalid request parameters.');
}

// Authorisation: record must belong to this user
$stmt = db()->prepare(
    'SELECT id, title, transcription_text, summary_text, status
     FROM summaries
     WHERE id = ? AND user_id = ? AND status <> \'deleted\'
     LIMIT 1'
);
$stmt->execute([$id, $uid]);
$record = $stmt->fetch();

if (!$record) {
    http_response_code(404);
    exit('Record not found or access denied.');
}

$text = ($type === 'transcription')
    ? ($record['transcription_text'] ?? '')
    : ($record['summary_text'] ?? '');

if (trim($text) === '') {
    http_response_code(404);
    exit('No content to download yet.');
}

$slug  = preg_replace('/[^a-z0-9_-]+/i', '-', $record['title']) ?: "record-{$id}";
$fname = "{$slug}_{$type}.txt";

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Content-Length: ' . strlen($text));
header('Cache-Control: no-store');

echo $text;
exit;
