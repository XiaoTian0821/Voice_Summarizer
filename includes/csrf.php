<?php
/**
 * includes/csrf.php
 * CSRF token helpers. Include at the top of every page with
 * state-changing forms or AJAX requests.
 *
 * Usage (in HTML form):
 *   require_once 'csrf.php';
 *   csrf_init();
 *   ...
 *   <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
 *
 * Usage (in AJAX):
 *   fetch('/api/xxx.php', {
 *       method: 'POST',
 *       headers: { 'X-CSRF-Token': window.CSRF_TOKEN },
 *       body: ...
 *   });
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_init(): void {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Return the current CSRF token (for embedding in HTML/JS).
 */
function csrf_token(): string {
    csrf_init();
    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF token on a server-side request.
 * Exits with 403 on mismatch.
 */
function csrf_verify(): void {
    csrf_init();
    $token = $_POST['csrf_token']
           ?? $_SERVER['HTTP_X_CSRF_TOKEN']
           ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}
