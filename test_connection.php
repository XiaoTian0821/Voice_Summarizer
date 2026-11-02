<?php
/**
 * test_connection.php – quick sanity check for the database.
 * Access: http://localhost/Voice_Summarizer/test_connection.php
 * Delete this file in production.
 */
require_once __DIR__ . '/includes/functions.php';

echo "<h2>AI Voice Summarizer – Connection Test</h2><pre>";

// 1. DB
try {
    $pdo = db();
    $row = $pdo->query('SELECT 1 AS ok')->fetch();
    echo "[OK] Database connection established.\n";
    echo "     Database: " . DB_NAME . "\n";
    echo "     PHP PDO:  " . PHP_VERSION . "\n";
} catch (Exception $e) {
    echo "[FAIL] Database connection failed: " . $e->getMessage() . "\n";
}

// 2. Table existence
try {
    $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn()
             ? 'users' : '';
    echo $tables ? "[OK] 'users' table exists.\n" : "[WARN] 'users' table not found – import the SQL file.\n";
    $s = $pdo->query("SHOW TABLES LIKE 'summaries'")->fetchColumn();
    echo $s ? "[OK] 'summaries' table exists.\n" : "[WARN] 'summaries' table not found.\n";
} catch (Exception $e) {
    echo "[FAIL] Table check: " . $e->getMessage() . "\n";
}

// 3. API key
echo "\n--- AI Configuration ---\n";
echo "Provider: " . AI_PROVIDER . "\n";
echo "API Base: " . AI_API_BASE . "\n";
echo "STT Model: " . STT_MODEL . "\n";
echo "Summary Model: " . SUMMARY_MODEL . "\n";

$keyOk = (AI_API_KEY !== 'sk-your-api-key-here');
echo $keyOk
    ? "[OK] API key is configured.\n"
    : "[WARN] API key is still the placeholder – transcription and summarization will not work until you set a real key in config/ai_config.php\n";

// 4. Storage dirs
$audioDir = AUDIO_STORAGE;
echo "\n--- Storage Directories ---\n";
echo "Audio storage: " . $audioDir . "\n";
echo is_dir($audioDir) ? "[OK] storage/audio exists.\n" : "[WARN] storage/audio does not exist – create it manually.\n";
$logsDir = dirname(AI_LOG_FILE);
echo is_dir($logsDir) ? "[OK] storage/logs exists.\n" : "[WARN] storage/logs does not exist – create it manually.\n";

echo "</pre>";
