<?php
// src/Bot.php
namespace AfghanCodeAI;

class Bot
{
    // ... properties and constructor are the same
    private TelegramService $telegram;
    private GeminiClient $gemini;
    private ChatHistory $history;

    public function __construct(TelegramService $telegram, GeminiClient $gemini, ChatHistory $history) { /* ... */ }

    public function handleUpdate(): void
    {
        error_log("INFO: Bot->handleUpdate() called."); // Radeyab 5
        // ... rest of handleUpdate is the same
    }

    private function processMessage(int $chat_id, string $user_message, int $message_id, array $user_info): void
    {
        error_log("INFO: Bot->processMessage() for chat {$chat_id} started."); // Radeyab 6
        try {
            // ... same logic as before
            $formatted_prompt = "[User: {$user_info['first_name']} (Username: @{$user_info['username']}, ID: {$user_info['id']})] says:\n{$user_message}";
            $history_contents = $this->history->load($chat_id);
            $geminiResponse = $this->gemini->getGeminiResponse($formatted_prompt, $history_contents);

            error_log("INFO: Gemini response type: " . ($geminiResponse['type'] ?? 'unknown')); // Radeyab 7

            if ($geminiResponse['type'] === 'function_call') {
                $this->handleFunctionCall($geminiResponse['data'], $chat_id, $message_id);
            } else {
                $this->handleTextResponse($geminiResponse['data'], $formatted_prompt, $chat_id, $message_id);
            }
        } catch (\Throwable $e) {
            // ... same catch block logic
        }
    }

    private function handleFunctionCall(array $functionCallData, int $chat_id, int $message_id): void
    {
        error_log("INFO: Bot->handleFunctionCall() for '{$functionCallData['name']}' called."); // Radeyab 8
        switch ($functionCallData['name']) {
            case 'send_private_message':
                $this->executeSendPrivateMessage($functionCallData['args'], $chat_id, $message_id);
                break;
            // ... other cases
        }
    }
    
    private function executeSendPrivateMessage(array $args, int $original_chat_id, int $original_message_id): void
    {
        $targetUserId = $args['user_id_to_send'] ?? null;
        $messageText = $args['message_text'] ?? null;
        error_log("INFO: Executing send_private_message to target {$targetUserId}."); // Radeyab 9

        if ($targetUserId && $messageText) {
            try {
                $this->telegram->sendMessage($messageText, (int)$targetUserId);
                $this->telegram->sendMessage("✅ پیام شما با موفقیت برای کاربر <b>{$targetUserId}</b> ارسال شد.", $original_chat_id, $original_message_id);
                error_log("SUCCESS: Private message sent and confirmation delivered."); // Radeyab 10
            } catch (\Throwable $e) {
                // ... same catch block
                $this->telegram->sendMessage("⚠️ مشکلی پیش اومد. من سعی کردم پیام رو بفرستم ولی نشد. مطمئن هستی کاربر ربات رو بلاک نکرده؟", $original_chat_id, $original_message_id);
                error_log("ERROR in executeSendPrivateMessage: " . $e->getMessage());
            }
        } else {
            error_log("ERROR: Missing arguments for send_private_message.");
            $this->telegram->sendMessage("⚠️ برای ارسال پیام، باید آیدی عددی و متن پیام رو مشخص کنی.", $original_chat_id, $original_message_id);
        }
    }

    // ... other methods are unchanged
}
