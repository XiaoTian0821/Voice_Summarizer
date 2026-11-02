<?php
/**
 * includes/functions.php
 * Shared helper functions for the AI Voice Summarizer.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ai_config.php';

/**
 * Sanitise a string for safe HTML output.
 */
function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Get the current logged-in user ID (null if not logged in).
 */
function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Require the user to be logged in.
 */
function require_login(): void {
    if (current_user_id() === null) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Generate a random file name for uploaded audio.
 */
function random_audio_filename(string $ext): string {
    return bin2hex(random_bytes(8)) . '_' . time() . '.' . strtolower($ext);
}

/**
 * Validate that an uploaded file is a permitted audio type.
 */
function validate_audio_upload(array $file): ?string {
    $name  = $file['name'];
    $ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mime  = mime_content_type($file['tmp_name']);

    if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
        return "Unsupported file extension '.{$ext}'. Allowed: " .
               implode(', ', ALLOWED_EXTENSIONS);
    }
    if (!in_array($mime, ALLOWED_MIME_TYPES, true)) {
        return "MIME type '{$mime}' is not a recognised audio format.";
    }
    if ($file['size'] > MAX_AUDIO_BYTES) {
        return sprintf(
            'File is %d bytes (max %d bytes / %.0f MB).',
            $file['size'], MAX_AUDIO_BYTES, MAX_AUDIO_BYTES / 1048576
        );
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'Upload failed with code ' . $file['error'] . '.';
    }
    return null; // valid
}

/**
 * Save an uploaded audio file to the storage directory.
 * Returns the stored filename on success, or null on failure.
 */
function store_audio(string $tmp, string $user_id, string $new_name): ?string {
    $dir = AUDIO_STORAGE . '/' . $user_id;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $dest = $dir . '/' . $new_name;
    if (!move_uploaded_file($tmp, $dest)) {
        error_log("[Storage] Failed to move uploaded file to {$dest}");
        return null;
    }
    return $new_name;
}

/**
 * Delete an audio file from storage (used when a summary is deleted).
 */
function delete_audio_file(string $user_id, string $filename): void {
    $path = AUDIO_STORAGE . '/' . $user_id . '/' . basename($filename);
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * Log a message to the AI log file.
 */
function ai_log(string $message): void {
    $dir = dirname(AI_LOG_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(
        AI_LOG_FILE,
        '[' . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

/**
 * Send a JSON response and stop.
 */
function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Require that the request comes from an authenticated user
 * and return the user ID.
 */
function require_auth_for_api(): int {
    if (!isset($_SESSION['user_id'])) {
        json_response(['ok' => false, 'message' => 'Not authenticated.'], 401);
    }
    return current_user_id();
}
