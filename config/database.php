<?php
/**
 * config/database.php
 * PDO connection for the AI Voice Summarizer.
 *
 * XAMPP default credentials:
 *   host = 127.0.0.1, user = root, password = (empty)
 *   db   = ai_voice_summarizer
 *
 * Adjust values below to match your environment.
 */

define('DB_HOST',     '127.0.0.1');
define('DB_NAME',     'ai_voice_summarizer');
define('DB_USER',     'root');
define('DB_PASS',     '123456');
define('DB_CHARSET',  'utf8mb4');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Log the real error, but do NOT expose it to the user.
            error_log('[DB] ' . $e->getMessage());
            http_response_code(500);
            exit('Database connection failed. Please check your configuration and try again.');
        }
    }
    return $pdo;
}
