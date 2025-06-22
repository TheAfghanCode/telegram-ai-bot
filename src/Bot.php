<?php
// src/Bot.php

namespace AfghanCodeAI;

class Bot
{
    private TelegramService $telegram;
    private GeminiClient $gemini;
    private ChatHistory $history;

    /**
     * Bot constructor.
     * Injects dependencies for Telegram, Gemini, and Chat History services.
     */
    public function __construct(TelegramService $telegram, GeminiClient $gemini, ChatHistory $history)
    {
        $this->telegram = $telegram;
        $this->gemini = $gemini;
        $this->history = $history;
    }

    /**
     * Handles the incoming update from Telegram.
     */
    public function handleUpdate(): void
    {
        $update = json_decode(file_get_contents('php://input'), true);

        if (isset($update['message']['text'])) {
            $chat_id = $update['message']['chat']['id'];
            $user_message = $update['message']['text'];
            $message_id = $update['message']['message_id'];

            error_log("INFO: New message received from ChatID: $chat_id");
            $this->processMessage($chat_id, $user_message, $message_id);
        }
    }

    /**
     * Processes the received message.
     */
    private function processMessage(int $chat_id, string $user_message, int $message_id): void
    {
        try {
            // Load the conversation history
            $history_contents = $this->history->load();

            // Get the response from the Gemini AI
            $final_ai_response = $this->gemini->getGeminiResponse($user_message, $history_contents);
            error_log("INFO: Response from Gemini: " . substr($final_ai_response, 0, 100) . "...");

            // Save the new interaction to the history, unless it's a moderation command
            if (trim($final_ai_response) !== '/warn') {
                $this->history->save($user_message, $final_ai_response);
            }
            
            // Determine the parse mode for the Telegram message.
            // Null for the /warn command, 'HTML' for all other AI-generated content.
            $parseMode = (trim($final_ai_response) === '/warn') ? null : 'HTML';

            // Send the response back to the user via Telegram
            $this->telegram->sendMessage($final_ai_response, $chat_id, $message_id, $parseMode);

        } catch (\Throwable $e) {
            // Log the processing error and notify the user
            error_log("--- PROCESSING ERROR ---: ChatID: $chat_id, Message: $user_message. Error: " . $e->getMessage());
            $this->telegram->sendMessage("<b>Sorry, an internal error occurred!</b> I'm working on it.", $chat_id, $message_id, 'HTML');
        }
    }
}
