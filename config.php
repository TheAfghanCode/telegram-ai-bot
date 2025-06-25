<?php
// config.php

/**
 * =================================================================
 * AfghanCodeAI - Central Configuration File
 * =================================================================
 * This file defines all constants and settings for the application.
 * It's the single source of truth for all configuration.
 */

// --- Core Secrets (loaded from Render Environment) ---
define('BOT_TOKEN', getenv('BOT_TOKEN'));
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY'));
define('DATABASE_URL', getenv('DATABASE_URL')); 
define('BOT_USERNAME', getenv('BOT_USERNAME')); // e.g., 'AfghanCodeAI_bot'

// --- Critical Environment Check ---
if (!BOT_TOKEN || !GEMINI_API_KEY || !DATABASE_URL || !BOT_USERNAME) { 
    die('FATAL: Required environment variables (BOT_TOKEN, GEMINI_API_KEY, DATABASE_URL, BOT_USERNAME) are not set.'); 
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

// --- NEW & CRITICAL: Central Security Key ---
// This key protects both backup_manager.php and admin_panel.php
// IMPORTANT: Change this to a long, random, and secret string!
define('ADMIN_SECRET_KEY', 'developer.alisina@Ali.1151494');

// --- Admin & Monitoring Configuration ---
define('ADMIN_USER_ID', 5133232659);
define('MONITORED_USER_ID', '5826521137');

// --- NEW: Archive Channel ---
// This is where the zipped history files will be sent.
define('LOG_CHANNEL_ARCHIVE', '-1002768477983');

// --- Telegram Logging & Archive Channels ---
define('LOG_CHANNEL_ARCHIVE', '-1002244335566'); //  شناسه کانال آرشیو را اینجا قرار بده
define('LOG_CHANNEL_ADMIN', '-1002655189872');
define('LOG_CHANNEL_USER', '-1002628189099');
define('LOG_CHANNEL_ALL', '-1002762407682');
define('LOG_CHANNEL_SYSTEM', '-1002781326891');

// --- Application Settings ---
define('MAX_HISTORY_LINES', 40);
define('UNLIMITED_HISTORY', true);
