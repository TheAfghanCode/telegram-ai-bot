<?php
// src/Bot.php

namespace AfghanCodeAI;

class Bot
{
    private TelegramService $telegram;
    private GeminiClient $gemini;
    private ChatHistory $history;

    public function __construct(TelegramService $telegram, GeminiClient $gemini, ChatHistory $history)
    {
        $this->telegram = $telegram;
        $this->gemini = $gemini;
        $this->history = $history;
    }

    public function handleUpdate(): void
    {
        $update = json_decode(file_get_contents('php://input'), true);

        if (isset($update['message']['text']) && isset($update['message']['from'])) {
            $message = $update['message'];
            $user_info = ['id' => $message['from']['id'], 'first_name' => $message['from']['first_name'] ?? '',];

            $this->processMessage($message['chat']['id'], $message['text'], $message['message_id'], $user_info);
        }
    }

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
            error_log("--- PROCESSING ERROR ---: ChatID: $chat_id, UserID: {$user_info['id']}. Error: " . $e->getMessage());
            $this->telegram->sendMessage("<b>متاسفم، یک خطای داخلی پیش اومد!</b> دارم روش کار می‌کنم.", $chat_id, $message_id, 'HTML');
        }
    }

    /**
     * Handles a standard text response from the AI.
     */
    private function handleTextResponse(string $ai_text, string $formatted_prompt, int $chat_id, int $message_id, int $userId): void
    {
        error_log("INFO: Text response from Gemini: " . substr($ai_text, 0, 100) . "...");
        if (trim($ai_text) !== '/warn') {
            $this->history->save($formatted_prompt, $ai_text, $userId);
        }
        $parseMode = (trim($ai_text) === '/warn') ? null : 'HTML';
        $this->telegram->sendMessage($ai_text, $chat_id, $message_id, $parseMode);
    }

    /**
     * Handles a function call request from the AI.
     */
    private function handleFunctionCall(array $functionCallData, int $original_chat_id, int $original_message_id): void
    {
        $functionName = $functionCallData['name'];
        $args = $functionCallData['args'];

        if ($functionName === 'send_private_message') {
            $targetUserId = $args['user_id_to_send'] ?? null;
            $messageText = $args['message_text'] ?? null;

            if ($targetUserId && $messageText) {
                try {
                    // Execute the function: send the private message
                    $this->telegram->sendMessage($messageText, (int)$targetUserId, null, 'HTML');

                    // Send a confirmation message back to the original user
                    $confirmationText = "✅ پیام شما با موفقیت برای کاربر <b>{$targetUserId}</b> ارسال شد.";
                    $this->telegram->sendMessage($confirmationText, $original_chat_id, $original_message_id, 'HTML');
                    error_log("SUCCESS: Executed 'send_private_message' to user {$targetUserId}.");
                } catch (\Throwable $e) {
                    $errorText = "⚠️ خطا در ارسال پیام. ممکن است کاربر ربات را مسدود کرده باشد.";
                    $this->telegram->sendMessage($errorText, $original_chat_id, $original_message_id, 'HTML');
                    error_log("ERROR: Failed to execute 'send_private_message'. Reason: " . $e->getMessage());
                }
            } else {
                $errorText = "⚠️ خطا: پارامترهای لازم (آیدی کاربر و متن پیام) در درخواست موجود نبود.";
                $this->telegram->sendMessage($errorText, $original_chat_id, $original_message_id, 'HTML');
            }
        }
    }
}
