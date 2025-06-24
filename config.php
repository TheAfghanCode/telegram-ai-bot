<?php
// config.php

define('BOT_TOKEN', getenv('BOT_TOKEN') ?: getenv("AI_AF_BOT_TOKEN"));
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY'));

if (!BOT_TOKEN || !GEMINI_API_KEY) {
    error_log('FATAL: Required environment variables are not set.');
    die('FATAL: Required environment variables are not set.');
}

// --- Directory Constants ---
define('CHAT_HISTORY_DIR', __DIR__ . '/history');
define('ARCHIVED_HISTORY_DIR', __DIR__ . '/archived_history'); // For deleted chats
define('PROMPT_TEMPLATE_PATH', __DIR__ . '/prompt_template.json');

// --- Application Settings ---
define('MAX_HISTORY_LINES', 30);
