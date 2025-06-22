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

        // We only process text messages for now
        if (isset($update['message']['text']) && isset($update['message']['from'])) {
            $message = $update['message'];
            $from = $message['from'];

            // --- NEW: Extract detailed user information ---
            $user_info = [
                'id' => $from['id'],
                'first_name' => $from['first_name'] ?? '',
                'last_name' => $from['last_name'] ?? '',
                'username' => $from['username'] ?? 'N/A',
            ];

            $chat_id = $message['chat']['id'];
            $user_message = $message['text'];
            $message_id = $message['message_id'];

            error_log("INFO: New message from UserID: {$user_info['id']} ({$user_info['username']}) in ChatID: $chat_id");
            
            // Process the message with the user's context
            $this->processMessage($chat_id, $user_message, $message_id, $user_info);
        }
    }

    /**
     * Processes the received message with user context.
     *
     * @param int $chat_id The ID of the chat.
     * @param string $user_message The raw text from the user.
     * @param int $message_id The ID of the message to reply to.
     * @param array $user_info Associative array with user details.
     */
    private function processMessage(int $chat_id, string $user_message, int $message_id, array $user_info): void
    {
        try {
            // --- NEW: Format the prompt to include user details ---
            // This format makes it clear to the AI who is speaking.
            $formatted_prompt = "[User: {$user_info['first_name']} {$user_info['last_name']} (Username: @{$user_info['username']}, ID: {$user_info['id']})] says:\n{$user_message}";
            
            // Load the conversation history
            $history_contents = $this->history->load();

            // Get the response from the Gemini AI using the formatted prompt
            $final_ai_response = $this->gemini->getGeminiResponse($formatted_prompt, $history_contents);
            error_log("INFO: Response from Gemini: " . substr($final_ai_response, 0, 100) . "...");

            // Save the new interaction to the history, unless it's a moderation command.
            // We save the *formatted* prompt to retain user context in history.
            if (trim($final_ai_response) !== '/warn') {
                $this->history->save($formatted_prompt, $final_ai_response);
            }
            
            // Determine the parse mode for the Telegram message.
            $parseMode = (trim($final_ai_response) === '/warn') ? null : 'HTML';

            // Send the response back to the user via Telegram
            $this->telegram->sendMessage($final_ai_response, $chat_id, $message_id, $parseMode);

        } catch (\Throwable $e) {
            // Log the processing error and notify the user
            error_log("--- PROCESSING ERROR ---: ChatID: $chat_id, UserID: {$user_info['id']}. Error: " . $e->getMessage());
            $this->telegram->sendMessage("<b>Sorry, an internal error occurred!</b> I'm working on it.", $chat_id, $message_id, 'HTML');
        }
    }
}
