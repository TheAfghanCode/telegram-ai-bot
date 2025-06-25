<?php
// ai-webhook-api-tg.php

/**
 * =================================================================
 * AfghanCodeAI - Main Webhook Entry Point
 * =================================================================
 * This file receives all incoming updates from Telegram, loads the
 * application, and triggers the main bot logic.
 */

// Give the script up to 2 minutes to run to prevent timeouts on slow AI responses.
set_time_limit(120);

// Setup error logging to a local file for critical infrastructure errors.
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/bot_errors.log');
error_reporting(E_ALL);

// Set header
header('Content-Type: text/html; charset=utf-8');

// --- Load Application Core ---
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/TelegramService.php';
require_once __DIR__ . '/src/LoggerService.php';
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
    // --- Dependency Injection Container ---
    // 1. Instantiate services with no dependencies first.
    $telegramService = new TelegramService(BOT_TOKEN);
    
    // 2. Instantiate services that depend on the first group.
    $logger = new LoggerService(BOT_TOKEN, LOG_CHANNEL_ADMIN, LOG_CHANNEL_USER, LOG_CHANNEL_ALL, LOG_CHANNEL_SYSTEM, LOG_CHANNEL_ARCHIVE);
    $chatHistory = new ChatHistory(MAX_HISTORY_LINES, $telegramService, $logger);
    $geminiClient = new GeminiClient(GEMINI_API_KEY, PROMPT_TEMPLATE_PATH);
    
    // 3. Instantiate the main Bot, injecting all required services.
    $bot = new Bot($telegramService, $geminiClient, $chatHistory, $logger);
    
    // 4. Handle the incoming update from Telegram.
    $bot->handleUpdate();

} catch (Throwable $e) {
    // This is the last line of defense for critical failures.
    error_log("--- FATAL UNHANDLED ERROR ---: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}

// Always respond to Telegram with a 200 OK to acknowledge receipt and prevent webhook retries.
http_response_code(200);
echo "OK";
