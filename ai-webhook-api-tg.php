<?php
// ai-webhook-api-tg.php

/**
 * Main entry point for the Telegram webhook.
 * This file is responsible for receiving the request, loading configuration and classes,
 * and executing the main bot logic.
 */

// --- Full Debug Mode Activation ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Ensure the web server has write permissions for this file
ini_set('error_log', __DIR__ . '/bot_errors.log');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

// Load configuration and core classes
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/ChatHistory.php';
require_once __DIR__ . '/src/GeminiClient.php';
require_once __DIR__ . '/src/TelegramService.php';
require_once __DIR__ . '/src/Bot.php';

// Use namespaces for better organization
use AfghanCodeAI\Bot;
use AfghanCodeAI\GeminiClient;
use AfghanCodeAI\TelegramService;
use AfghanCodeAI\ChatHistory;

try {
    // Create an instance of the Telegram service
    $telegramService = new TelegramService(BOT_TOKEN);

    // Create an instance of the Gemini client
    $geminiClient = new GeminiClient(GEMINI_API_KEY, PROMPT_TEMPLATE_PATH);

    // Create an instance of the chat history manager
    $chatHistory = new ChatHistory(CHAT_HISTORY_FILE, MAX_HISTORY_LINES);

    // Create and run the main bot
    $bot = new Bot($telegramService, $geminiClient, $chatHistory);
    $bot->handleUpdate();

} catch (Throwable $e) {
    // Log fatal errors that were not caught elsewhere
    error_log("--- FATAL ERROR ---: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    // Always return a 200 OK response to prevent Telegram from retrying
    http_response_code(200);
    echo "An error occurred.";
}

// Always respond with a 200 OK to Telegram to acknowledge receipt of the update
http_response_code(200);
echo "OK";
