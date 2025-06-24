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

        if (isset($update['message']['text']) && isset($update['message']['from']) && isset($update['message']['chat'])) {
            $message = $update['message'];
            $chat_id = $message['chat']['id'];
            $user_id = $message['from']['id'];
            $user_message = $message['text'];
            
            // --- NEW: Admin Command Gatekeeper ---
            // Check if the message is a global command from the admin.
            if ($user_id === ADMIN_USER_ID && str_starts_with($user_message, 'دستور عمومی:')) {
                $this->handleAdminCommand($user_message, $chat_id, $message['message_id']);
                return; // Stop further processing
            }

            // If not an admin command, proceed with normal processing.
            $user_info = ['id' => $user_id, 'first_name' => $message['from']['first_name'] ?? '', 'username' => $message['from']['username'] ?? 'N/A'];
            $this->processMessage($chat_id, $user_message, $message['message_id'], $user_info);
        }
    }

    /**
     * NEW: Handles global commands from the admin.
     */
    private function handleAdminCommand(string $raw_command, int $chat_id, int $message_id): void
    {
        // Extract the actual instruction from the command
        $instruction = trim(str_replace('دستور عمومی:', '', $raw_command));

        if (!empty($instruction)) {
            // Append the new rule to the public memory file
            file_put_contents(PUBLIC_MEMORY_FILE, $instruction . PHP_EOL, FILE_APPEND | LOCK_EX);
            $responseText = "✅ دستور عمومی با موفقیت ثبت شد و از این پس برای تمام کاربران اعمال می‌شود.";
            error_log("ADMIN COMMAND: New global rule added by admin " . ADMIN_USER_ID . ": " . $instruction);
        } else {
            $responseText = "⚠️ دستور عمومی نمی‌تواند خالی باشد.";
        }
        
        $this->telegram->sendMessage($responseText, $chat_id, $message_id);
    }

    // processMessage, handleTextResponse, and handleFunctionCall methods remain unchanged.
    // They will now be affected by the global rules injected by GeminiClient.
    private function processMessage(int $chat_id, string $user_message, int $message_id, array $user_info): void
    {
        // ... same logic as before
        try {
            $formatted_prompt = "[User: {$user_info['first_name']} (Username: @{$user_info['username']}, ID: {$user_info['id']})] says:\n{$user_message}";
            $history_contents = $this->history->load($chat_id);
            $geminiResponse = $this->gemini->getGeminiResponse($formatted_prompt, $history_contents);

            if ($geminiResponse['type'] === 'function_call') {
                $this->handleFunctionCall($geminiResponse['data'], $chat_id, $message_id);
            } else {
                $this->handleTextResponse($geminiResponse['data'], $formatted_prompt, $chat_id, $message_id);
            }
        }
            catch (\Throwable $e) {
                $error_message = $e->getMessage();
                // NEW: Add the problematic text to the log for easy debugging.
                if (str_contains($error_message, "Telegram API error")) {
                     error_log("--- PROCESSING ERROR ---: ChatID: $chat_id, UserID: {$user_info['id']}. Error: {$error_message}. Problematic Text: " . $geminiResponse['data'] ?? 'N/A');
                } else {
                     error_log("--- PROCESSING ERROR ---: ChatID: $chat_id, UserID: {$user_info['id']}. Error: " . $error_message);
                }
                $this->telegram->sendMessage("<b>متاسفم، یک خطای داخلی پیش اومد!</b>", $chat_id, $message_id);
            }
       
    }

    private function handleTextResponse(string $ai_text, string $formatted_prompt, int $chat_id, int $message_id): void
    {
        // ... same logic as before
        if (trim($ai_text) !== '/warn') {
            $this->history->save($formatted_prompt, $ai_text, $chat_id);
        }
        $this->telegram->sendMessage($ai_text, $chat_id, $message_id);
    }

    private function handleFunctionCall(array $functionCallData, int $chat_id, int $message_id): void
    {
        // ... same logic as before using the switch case
        switch ($functionCallData['name']) {
            case 'send_private_message':
                // ... logic
                break;
            case 'delete_chat_history':
                $this->executeDeleteChatHistory($chat_id, $message_id);
                break;
        }
    }
    
    private function executeDeleteChatHistory(int $chat_id, int $user_command_message_id): void
    {
        // ... same logic as before
        if ($this->history->archive($chat_id)) {
            $this->telegram->sendMessage("✅ درخواست شما انجام شد. تمام سابقه گفتگوی ما پاک شد و من دیگه بهش دسترسی ندارم.", $chat_id);
            $this->telegram->deleteMessage($chat_id, $user_command_message_id);
        } else {
            $this->telegram->sendMessage("⚠️ مشکلی در آرشیو کردن سابقه گفتگو پیش آمد.", $chat_id, $user_command_message_id);
        }
    }
    // ... other execute functions
}
