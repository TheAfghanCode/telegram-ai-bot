<?php
// config.php

/**
 * =================================================================
 * AfghanCodeAI - Central Configuration File (Guarded Version)
 * =================================================================
 * This include guard prevents the file from being processed more than once,
 * avoiding "Constant already defined" errors.
 */

if (defined('CONFIG_LOADED')) {
    return; // Exit if the config has already been loaded.
}

// Mark the configuration as loaded.
define('CONFIG_LOADED', true);

// --- Core Secrets (loaded from Render Environment) ---
define('BOT_TOKEN', getenv('BOT_TOKEN'));
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY'));
define('DATABASE_URL', getenv('DATABASE_URL')); 
define('BOT_USERNAME', getenv('BOT_USERNAME')); 

if (!BOT_TOKEN || !GEMINI_API_KEY || !DATABASE_URL || !BOT_USERNAME) { 
    die('FATAL: Required environment variables are not set.'); 
}

// --- Central Security Key ---
define('ADMIN_SECRET_KEY', '12345678');

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

// --- Admin & Monitoring Configuration ---
define('ADMIN_USER_ID', 5133232659);
define('MONITORED_USER_ID', '5826521137');

// --- Telegram Logging & Archive Channels ---
define('LOG_CHANNEL_ARCHIVE', '-1002768477983');
define('LOG_CHANNEL_ADMIN', '-1002655189872');
define('LOG_CHANNEL_USER', '-1002628189099');
define('LOG_CHANNEL_ALL', '-1002762407682');
define('LOG_CHANNEL_SYSTEM', '-1002781326891');

// --- Application Settings ---
define('MAX_HISTORY_LINES', 40);
define('UNLIMITED_HISTORY', true);
