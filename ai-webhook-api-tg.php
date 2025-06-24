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
    // UPDATED: Correctly instantiate all services
    $telegramService = new TelegramService(BOT_TOKEN);
    $chatHistory = new ChatHistory(CHAT_HISTORY_DIR, ARCHIVED_HISTORY_DIR, MAX_HISTORY_LINES);
    $geminiClient = new GeminiClient(GEMINI_API_KEY, PROMPT_TEMPLATE_PATH);
    
    // Inject dependencies into the Bot
    $bot = new Bot($telegramService, $geminiClient, $chatHistory);
    $bot->handleUpdate();

} catch (Throwable $e) {
    error_log("--- FATAL ERROR ---: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}

// Always respond to Telegram to prevent retries
http_response_code(200);
echo "OK";
