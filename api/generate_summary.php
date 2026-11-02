<?php
/**
 * api/generate_summary.php
 * AJAX endpoint: generates an AI summary for a stored transcription.
 *
 * POST (FormData fields):
 *   - id:                 summary record ID
 *   - summary_type:       short | detailed | bullets | meeting_notes | study_notes | custom
 *   - custom_instruction: optional custom instruction text
 *   - language:           output language code (en, zh, es, fr, de, ja)
 *   - csrf_token:         CSRF token
 *
 * Returns JSON:
 *   { ok: true,  summary: "..." }
 *   { ok: false, message: "..." }
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$uid = require_auth_for_api();
csrf_verify();

// Parse input
$id              = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$summaryType     = isset($_POST['summary_type']) ? $_POST['summary_type'] : 'short';
$customInstr     = isset($_POST['custom_instruction']) ? trim($_POST['custom_instruction']) : '';
$language        = isset($_POST['language']) ? $_POST['language'] : 'en';

$validTypes = ['short','detailed','bullets','meeting_notes','study_notes','custom'];
if (!in_array($summaryType, $validTypes, true)) {
    json_response(['ok' => false, 'message' => 'Invalid summary type.'], 400);
}

// Load record – must belong to this user
$stmt = db()->prepare(
    'SELECT id, user_id, title, audio_filename, transcription_text, summary_text
     FROM summaries
     WHERE id = ? AND user_id = ? AND status <> \'deleted\'
     LIMIT 1'
);
$stmt->execute([$id, $uid]);
$record = $stmt->fetch();

if (!$record || empty($record['transcription_text'])) {
    json_response(['ok' => false, 'message' => 'No transcription found for this record.'], 404);
}

// Build the system prompt
$promptMap = [
    'short'         => 'Write a short 3–5 sentence summary of the following transcription. '
                      . 'Preserve the main meaning and do not add any new information.',
    'detailed'      => 'Write a detailed summary of the following transcription. '
                      . 'Cover all important points, context, and conclusions. Use clear headings where helpful.',
    'bullets'       => 'List the key points of the following transcription as a bulleted list. '
                      . 'Each bullet should be one clear point. Do not invent information.',
    'meeting_notes' => 'Convert the following transcription into structured meeting notes. '
                      . 'Use these sections: Key Discussion Points, Decisions Made, Action Items (with owners if named), '
                      . 'Open Questions. Mark anything unclear as [Unclear / Needs Verification].',
    'study_notes'   => 'Convert the following transcription into study notes. '
                      . 'Use clear definitions, key concepts, and revision points suitable for exam preparation. '
                      . 'Do not invent information.',
    'custom'        => 'You are a professional summarizer. Summarize the following transcription '
                      . 'according to the user\'s custom instructions.',
];

$systemPrompt = $promptMap[$summaryType] ?? $promptMap['short'];

// Append custom instruction if provided
if ($summaryType === 'custom' && $customInstr !== '') {
    $systemPrompt .= " User instructions: " . $customInstr;
}

// Language instruction
$langLabels = [
    'en' => 'English', 'zh' => 'Chinese (Simplified)', 'es' => 'Spanish',
    'fr' => 'French',  'de' => 'German',               'ja' => 'Japanese',
];
$systemPrompt .= " Write the summary in " . ($langLabels[$language] ?? 'English') . ".";

$userContent = "Transcription text:\n" . $record['transcription_text'];

// Build messages
$messages = [
    ['role' => 'system', 'content' => $systemPrompt],
    ['role' => 'user',   'content' => $userContent],
];

// Call the LLM API
$apiKey = AI_API_KEY;
if ($apiKey === 'sk-your-api-key-here') {
    json_response(['ok' => false, 'message' => 'AI API key is not configured. Please set your API key in config/ai_config.php.'], 500);
}

$ch = curl_init(AI_API_BASE . '/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST          => true,
    CURLOPT_TIMEOUT       => 120,
    CURLOPT_HTTPHEADER    => [
        "Authorization: Bearer " . AI_API_KEY,
        "Content-Type: application/json",
    ],
    CURLOPT_POSTFIELDS    => json_encode([
        'model'    => SUMMARY_MODEL,
        'messages' => $messages,
        'max_tokens' => 2000,
        'temperature' => 0.4,
    ]),
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($response === false) {
    ai_log("LLM cURL error: {$curlErr}");
    json_response(['ok' => false, 'message' => 'AI API network error. Please try again later.'], 502);
}

$data = json_decode($response, true);

// Check for API errors
if ($httpCode !== 200) {
    $errMsg = $data['error']['message'] ?? mb_substr($response, 0, 300);
    ai_log("LLM API error ({$httpCode}): {$errMsg}");

    // Detect rate limit
    if ($httpCode === 429) {
        json_response(['ok' => false, 'message' => 'API rate limit exceeded. Please wait a moment and try again.'], 429);
    }
    json_response(['ok' => false, 'message' => "AI API error: {$errMsg}"], 502);
}

$summaryText = $data['choices'][0]['message']['content'] ?? null;
if ($summaryText === null || trim($summaryText) === '') {
    ai_log('LLM returned an empty summary.');
    json_response(['ok' => false, 'message' => 'The AI returned an empty summary. Please try again.'], 500);
}

$summaryText = trim($summaryText);

// Save to database
$stmt = db()->prepare(
    'UPDATE summaries
     SET summary_text = ?, summary_type = ?, custom_instruction = ?,
         language = ?, status = \'summarized\', updated_at = NOW()
     WHERE id = ? AND user_id = ?'
);
$stmt->execute([$summaryText, $summaryType, $customInstr ?: null, $language, $id, $uid]);

json_response(['ok' => true, 'summary' => $summaryText]);
