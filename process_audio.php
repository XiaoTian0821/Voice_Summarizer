<?php
/**
 * process_audio.php
 * API endpoint: receives an uploaded or recorded audio file,
 * saves it to storage, then calls the speech-to-text API.
 *
 * POST fields:
 *   - csrf_token:    CSRF token (via POST or X-CSRF-Token header)
 *   - audio_file:    uploaded file (browser file input)
 *   - recorded_audio: recorded blob (MediaRecorder)
 *
 * Returns JSON:
 *   { ok: true,  id: <int>, transcription: "..." }
 *   { ok: false, message: "..." }
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

// 1. Require authentication
$uid = require_auth_for_api();

// 2. Verify CSRF
csrf_verify();

// 3. Determine which file was sent
$file = null;
if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['audio_file'];
} elseif (isset($_FILES['recorded_audio']) && $_FILES['recorded_audio']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['recorded_audio'];
}

if (!$file) {
    json_response(['ok' => false, 'message' => 'No audio file received.'], 400);
}

// 4. Validate file
$err = validate_audio_upload($file);
if ($err) {
    json_response(['ok' => false, 'message' => $err], 422);
}

// 5. Save to storage
$newName = random_audio_filename(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'webm');
$stored  = store_audio($file['tmp_name'], (string)$uid, $newName);
if ($stored === null) {
    json_response(['ok' => false, 'message' => 'Could not save the audio file on the server.'], 500);
}

$originalName = $file['name'] ?: $newName;

// 6. Call the speech-to-text API
$transcription = transcribe_audio_file($uid, $newName);
if ($transcription === null) {
    // Clean up the file on failure
    delete_audio_file((string)$uid, $newName);
    json_response(['ok' => false, 'message' => 'Speech-to-text API failed. Please try again later.'], 502);
}

// 7. Create / update summary record
$title = preg_replace('/\.[^.]+$/', '', $originalName) ?: 'Untitled';
$stmt  = db()->prepare(
    'INSERT INTO summaries
        (user_id, title, audio_filename, transcription_text, summary_type, status, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
);
$stmt->execute([
    $uid,
    mb_substr($title, 0, 250),
    $newName,
    $transcription,
    'short',
    'transcribed',
]);
$newId = (int)db()->lastInsertId();

json_response([
    'ok'           => true,
    'id'           => $newId,
    'transcription'=> $transcription,
]);

// ────────────────────────────────────────────────────────────────────────
// Internal helper: call the STT API
// ────────────────────────────────────────────────────────────────────────
function transcribe_audio_file(int $user_id, string $stored_filename): ?string {
    $filePath = AUDIO_STORAGE . '/' . $user_id . '/' . $stored_filename;
    if (!is_file($filePath)) return null;

    $apiKey = AI_API_KEY;
    if ($apiKey === 'sk-your-api-key-here') {
        ai_log("STT skipped: API key not configured.");
        json_response([
            'ok' => false,
            'message' => 'Speech-to-text is not configured. Add your API key in config/ai_config.php.',
        ], 503);
    }

    // Build multipart request
    $boundary = '----form-boundary-' . bin2hex(random_bytes(12));
    $ext      = pathinfo($stored_filename, PATHINFO_EXTENSION);
    $mime     = match($ext) {
        'mp3'  => 'audio/mpeg',
        'wav'  => 'audio/wav',
        'm4a'  => 'audio/mp4',
        'webm' => 'audio/webm',
        default => 'application/octet-stream',
    };

    $fileContents = file_get_contents($filePath);

    $body = "--{$boundary}\r\n"
          . "Content-Disposition: form-data; name=\"file\"; filename=\"recording.{$ext}\"\r\n"
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
            "Authorization: Bearer " . AI_API_KEY,
            "Content-Type: multipart/form-data; boundary={$boundary}",
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        ai_log("cURL error: {$curlErr}");
        return null;
    }

    $data = json_decode($response, true);
    if ($httpCode !== 200 || !isset($data['text'])) {
        ai_log("STT API error ({$httpCode}): " . mb_substr($response, 0, 500));
        return null;
    }

    return trim($data['text']);
}
