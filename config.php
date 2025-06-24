<?php
// config.php

/**
 * Central configuration file for the project.
 */

// --- Read Environment Variables ---
$botToken = getenv('BOT_TOKEN') ?: getenv("AI_AF_BOT_TOKEN");
$geminiApiKey = getenv('GEMINI_API_KEY');

if (!$botToken || !$geminiApiKey) {
    error_log('FATAL: Required environment variables (BOT_TOKEN, GEMINI_API_KEY) are not set.');
    die('FATAL: Required environment variables are not set.');
}

define('BOT_TOKEN', $botToken);
define('GEMINI_API_KEY', $geminiApiKey);

// --- Application Constants ---
define('CHAT_HISTORY_DIR', __DIR__ . '/history');
define('MAX_HISTORY_LINES', 20); // Used only if UNLIMITED_HISTORY is false
define('PROMPT_TEMPLATE_PATH', __DIR__ . '/prompt_template.json');

// --- NEW: Memory Configuration ---
/**
 * Set to true for unlimited chat history for each user.
 * Set to false to limit history to MAX_HISTORY_LINES.
 */
define('UNLIMITED_HISTORY', false);
