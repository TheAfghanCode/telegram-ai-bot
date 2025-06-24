<?php
// ai-webhook-api-tg.php

/**
 * Main entry point for the Telegram webhook.
 * This file is responsible for receiving the request, loading all configurations and classes,
 * instantiating all services, and executing the main bot logic.
 */

// Give the script up to 2 minutes to run to prevent timeouts on slow AI responses.
set_time_limit(120);

// Setup error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/bot_errors.log');
error_reporting(E_ALL);

// Set header
header('Content-Type: text/html; charset=utf-8');

// Load all required files in the correct order
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/TelegramService.php';
require_once __DIR__ . '/src/LoggerService.php'; // The new Logger
require_once __DIR__ . '/src/ChatHistory.php';
require_once __DIR__ . '/src/GeminiClient.php';
require_once __DIR__ . '/src/Bot.php';

// Use namespaces for clean code
use AfghanCodeAI\Bot;
use AfghanCodeAI\GeminiClient;
use AfghanCodeAI\TelegramService;
use AfghanCodeAI\ChatHistory;
use AfghanCodeAI\LoggerService;

try {
    // Instantiate all services with the correct arguments from config.php
    $telegramService = new TelegramService(BOT_TOKEN);
    $chatHistory = new ChatHistory(CHAT_HISTORY_DIR, ARCHIVED_HISTORY_DIR, MAX_HISTORY_LINES);
    $geminiClient = new GeminiClient(GEMINI_API_KEY, PROMPT_TEMPLATE_PATH, PUBLIC_MEMORY_FILE);
    
    // Create the new Logger instance
    $logger = new LoggerService(BOT_TOKEN, LOG_CHANNEL_ADMIN, LOG_CHANNEL_USER, LOG_CHANNEL_ALL, LOG_CHANNEL_SYSTEM);

    // Instantiate the main Bot, injecting all dependencies including the new logger
    $bot = new Bot($telegramService, $geminiClient, $chatHistory, $logger);
    
    // Handle the incoming update
    $bot->handleUpdate();

} catch (Throwable $e) {
    // Catch any uncaught exception and log it to the main error file.
    // This is the last line of defense.
    error_log("--- FATAL UNHANDLED ERROR ---: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}

// Always respond to Telegram with a 200 OK to acknowledge receipt and prevent webhook retries.
http_response_code(200);
echo "OK";
