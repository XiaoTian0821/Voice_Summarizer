<?php
/**
 * api/transcribe.php
 * Standalone AJAX endpoint for re-transcribing an already-stored
 * audio file (e.g. retry a failed transcription).
 *
 * POST (JSON body):
 *   { "id": <summary_id> }
 *
 * Returns JSON:
 *   { ok: true, transcription: "..." }
 *   { ok: false, message: "..." }
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$uid = require_auth_for_api();
csrf_verify();

$raw = json_decode(file_get_contents('php://input'), true);
$id  = isset($raw['id']) ? (int)$raw['id'] : 0;

if ($id <= 0) {
    json_response(['ok' => false, 'message' => 'Missing summary ID.'], 400);
}

// Load record (must belong to this user and not be deleted)
$stmt = db()->prepare(
    'SELECT id, user_id, audio_filename FROM summaries
     WHERE id = ? AND user_id = ? AND status <> \'deleted\' LIMIT 1'
);
$stmt->execute([$id, $uid]);
$record = $stmt->fetch();

if (!$record) {
    json_response(['ok' => false, 'message' => 'Record not found or access denied.'], 404);
}

// Call STT API directly
$apiKey = AI_API_KEY;
if ($apiKey === 'sk-your-api-key-here') {
    json_response(['ok' => false, 'message' => 'API key not configured.'], 500);
}

$stored_file = $record['audio_filename'];
$filePath    = AUDIO_STORAGE . '/' . $uid . '/' . $stored_file;
if (!is_file($filePath)) {
    json_response(['ok' => false, 'message' => 'Audio file not found on server.'], 404);
}

$boundary   = '----retry-' . bin2hex(random_bytes(8));
$ext        = pathinfo($stored_file, PATHINFO_EXTENSION);
$mime       = match($ext) {
    'mp3'  => 'audio/mpeg',
    'wav'  => 'audio/wav',
    'm4a'  => 'audio/mp4',
    'webm' => 'audio/webm',
    default => 'application/octet-stream',
};
$fileContents = file_get_contents($filePath);

$body = "--{$boundary}\r\n"
      . "Content-Disposition: form-data; name=\"file\"; filename=\"retry.{$ext}\"\r\n"
      . "Content-Type: {$mime}\r\n\r\n"
      . $fileContents . "\r\n"
      . "--{$boundary}\r\n"
      . "Content-Disposition: form-data; name=\"model\"\r\n\r\n"
      . STT_MODEL . "\r\n"
      . "--{$boundary}\r\n"
      . "Content-Disposition: form-data; name=\"language\"\r\n\r\n"
      . "en\r\n"
      . "--{$boundary}--\r\n";

$ch = curl_init(AI_API_BASE . '/audio/transcriptions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST          => true,
    CURLOPT_POSTFIELDS    => $body,
    CURLOPT_TIMEOUT       => 120,
    CURLOPT_HTTPHEADER    => [
        "Authorization: Bearer {$apiKey}",
        "Content-Type: multipart/form-data; boundary={$boundary}",
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    ai_log("Retry STT cURL error.");
    json_response(['ok' => false, 'message' => 'Transcription failed (network error).'], 502);
}

$data = json_decode($response, true);
if ($httpCode !== 200 || !isset($data['text'])) {
    ai_log("Retry STT API error ({$httpCode}): " . mb_substr($response, 0, 500));
    json_response(['ok' => false, 'message' => 'Transcription failed. Please check your API key and try again.'], 502);
}

$text = trim($data['text']);

// Update record
$stmt = db()->prepare(
    'UPDATE summaries
     SET transcription_text = ?, status = \'transcribed\', updated_at = NOW()
     WHERE id = ? AND user_id = ?'
);
$stmt->execute([$text, $id, $uid]);

json_response(['ok' => true, 'transcription' => $text]);
