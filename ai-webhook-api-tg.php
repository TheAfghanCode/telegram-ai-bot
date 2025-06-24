<?php
// ai-webhook-api-tg.php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/bot_errors.log');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

// Load all required files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/TelegramService.php';
require_once __DIR__ . '/src/ChatHistory.php';
require_once __DIR__ . '/src/GeminiClient.php';
require_once __DIR__ . '/src/Bot.php';

use AfghanCodeAI\Bot;
use AfghanCodeAI\GeminiClient;
use AfghanCodeAI\TelegramService;
use AfghanCodeAI\ChatHistory;

try {
    // Instantiate all services with the correct arguments
    $telegramService = new TelegramService(BOT_TOKEN);
    $chatHistory = new ChatHistory(CHAT_HISTORY_DIR, ARCHIVED_HISTORY_DIR, MAX_HISTORY_LINES);
    
    // --- THE FIX IS HERE ---
    // We now pass the third required argument (PUBLIC_MEMORY_FILE) to the GeminiClient constructor.
    $geminiClient = new GeminiClient(GEMINI_API_KEY, PROMPT_TEMPLATE_PATH, PUBLIC_MEMORY_FILE);
    
    // Inject all dependencies into the Bot
    $bot = new Bot($telegramService, $geminiClient, $chatHistory);
    $bot->handleUpdate();

} catch (Throwable $e) {
    // Log any fatal errors that occur during the process
    error_log("--- FATAL ERROR ---: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}

// Always respond to Telegram with a 200 OK to prevent webhook retries
http_response_code(200);
echo "OK";
