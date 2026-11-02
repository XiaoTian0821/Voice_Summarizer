<?php
/**
 * delete_summary.php
 * DELETE endpoint: soft-deletes a summary record and removes the audio file.
 *
 * POST (FormData):
 *   - id:         summary record ID
 *   - csrf_token: CSRF token
 *
 * Returns JSON:
 *   { ok: true }
 *   { ok: false, message: "..." }
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

$uid = require_auth_for_api();
csrf_verify();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    json_response(['ok' => false, 'message' => 'Missing or invalid ID.'], 400);
}

// Load record – must belong to this user and not already deleted
$stmt = db()->prepare(
    'SELECT id, user_id, audio_filename, status FROM summaries
     WHERE id = ? AND user_id = ? AND status <> \'deleted\'
     LIMIT 1'
);
$stmt->execute([$id, $uid]);
$record = $stmt->fetch();

if (!$record) {
    json_response(['ok' => false, 'message' => 'Record not found or access denied.'], 404);
}

// Mark as deleted in the database
$stmt = db()->prepare(
    'UPDATE summaries SET status = \'deleted\', updated_at = NOW() WHERE id = ? AND user_id = ?'
);
$stmt->execute([$id, $uid]);

// Remove the audio file from storage
delete_audio_file((string)$uid, $record['audio_filename']);

json_response(['ok' => true]);
