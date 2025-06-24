<?php
// config.php

define('BOT_TOKEN', getenv('BOT_TOKEN') ?: getenv("AI_AF_BOT_TOKEN"));
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY'));

if (!BOT_TOKEN || !GEMINI_API_KEY) {
    die('FATAL: Required environment variables are not set.');
}

// --- Directory & File Constants ---
define('CHAT_HISTORY_DIR', __DIR__ . '/history');
define('ARCHIVED_HISTORY_DIR', __DIR__ . '/archived_history');
define('PROMPT_TEMPLATE_PATH', __DIR__ . '/prompt_template.json');
define('PUBLIC_MEMORY_FILE', __DIR__ . '/public_memory.log');

// --- Admin Configuration ---
define('ADMIN_USER_ID', 5133232659);

// --- Application Settings ---
define('MAX_HISTORY_LINES', 30); // Used only if UNLIMITED_HISTORY is false

// --- NEW: Unlimited Memory Switch ---
/**
 * Set to true for unlimited chat history for each user and group.
 * Set to false to limit history to MAX_HISTORY_LINES.
 */
define('UNLIMITED_HISTORY', true);
