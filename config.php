<?php
// config.php

// --- Core Secrets (loaded from Render Environment) ---
define('BOT_TOKEN', getenv('BOT_TOKEN'));
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY'));
define('DATABASE_URL', getenv('DATABASE_URL')); 

if (!BOT_TOKEN || !GEMINI_API_KEY || !DATABASE_URL) { 
    die('FATAL: Required environment variables (BOT_TOKEN, GEMINI_API_KEY, DATABASE_URL) are not set.'); 
}

// --- Parse Database URL into individual components ---
$dbInfo = parse_url(DATABASE_URL);
define('DB_HOST', $dbInfo['host'] ?? null);
define('DB_PORT', $dbInfo['port'] ?? 5432);
define('DB_NAME', ltrim($dbInfo['path'] ?? '', '/'));
define('DB_USER', $dbInfo['user'] ?? null);
define('DB_PASS', $dbInfo['pass'] ?? null);

if (!DB_HOST || !DB_NAME || !DB_USER) {
    die('FATAL: Could not parse DATABASE_URL correctly.');
}

// --- File-based constants ---
define('PROMPT_TEMPLATE_PATH', __DIR__ . '/prompt_template.json');
// Public memory can still be a file for simplicity
define('PUBLIC_MEMORY_FILE', __DIR__ . '/public_memory.log');

// --- Admin & Monitoring ---
define('ADMIN_USER_ID', 5133232659);
define('MONITORED_USER_ID', '5826521137'); // Replace with a numeric ID

// --- Logging Channels ---
define('LOG_CHANNEL_ADMIN', '-1002655189872');
define('LOG_CHANNEL_USER', '-1002628189099');
define('LOG_CHANNEL_ALL', '-1002762407682');
define('LOG_CHANNEL_SYSTEM', '-1002781326891');

// --- Application Settings ---
define('MAX_HISTORY_LINES', 40);
define('UNLIMITED_HISTORY', false);
