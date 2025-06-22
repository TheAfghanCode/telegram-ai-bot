<?php
// config.php

/**
 * Central configuration file for the project.
 * All environment variables, tokens, and application constants are defined here.
 */

// --- Read Environment Variables ---
// This approach is highly flexible for different environments (like Docker or various servers)
$botToken = getenv('BOT_TOKEN') ?: getenv("AI_AF_BOT_TOKEN");
$geminiApiKey = getenv('GEMINI_API_KEY');

if (!$botToken || !$geminiApiKey) {
    // If key variables are missing, stop the script and log the error
    error_log('FATAL: Required environment variables (BOT_TOKEN, GEMINI_API_KEY) are not set.');
    die('FATAL: Required environment variables are not set.');
}

define('BOT_TOKEN', $botToken);
define('GEMINI_API_KEY', $geminiApiKey);

// --- Application Constants ---

// Path to the chat history log file
define('CHAT_HISTORY_FILE', __DIR__ . '/chat_history.log');

// Maximum number of lines (user and model messages) to keep in the history
define('MAX_HISTORY_LINES', 100);

// Path to the prompt template JSON file
define('PROMPT_TEMPLATE_PATH', __DIR__ . '/prompt_template.json');
