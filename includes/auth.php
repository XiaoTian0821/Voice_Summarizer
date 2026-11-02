<?php
/**
 * includes/auth.php
 * Include at the top of every page that requires a logged-in user.
 *
 * Usage:
 *   require_once __DIR__ . '/includes/auth.php';
 *   auth_guard();   // redirects to login if not logged in
 */

require_once __DIR__ . '/functions.php';
session_start();

function auth_guard(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Get the current user's name (from session, cached on login).
 */
function current_user_name(): string {
    return $_SESSION['user_name'] ?? 'User';
}
