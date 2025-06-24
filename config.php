<?php
// config.php

define('BOT_TOKEN', getenv('BOT_TOKEN') ?: getenv("AI_AF_BOT_TOKEN"));
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY'));

if (!BOT_TOKEN || !GEMINI_API_KEY) { die('FATAL: Required environment variables are not set.'); }

// --- Directory & File Constants ---
define('CHAT_HISTORY_DIR', __DIR__ . '/history');
define('ARCHIVED_HISTORY_DIR', __DIR__ . '/archived_history');
define('PROMPT_TEMPLATE_PATH', __DIR__ . '/prompt_template.json');
define('PUBLIC_MEMORY_FILE', __DIR__ . '/public_memory.log');

// --- Admin & Monitoring Configuration ---
define('ADMIN_USER_ID', 5133232659);
define('MONITORED_USER_ID', 'ID_OF_USER_TO_MONITOR'); //  آیدی عددی کاربر دوم را اینجا قرار بده

// --- NEW: Telegram Logging Channels ---
//  شناسه‌های عددی کانال‌های خصوصی خود را اینجا قرار بده
define('LOG_CHANNEL_ADMIN', '-1002655189872');
define('LOG_CHANNEL_USER', '-1002628189099');
define('LOG_CHANNEL_ALL', '-1002762407682');
define('LOG_CHANNEL_SYSTEM', '-1002781326891');

// --- Application Settings ---
define('MAX_HISTORY_LINES', 30);
define('UNLIMITED_HISTORY', false);
