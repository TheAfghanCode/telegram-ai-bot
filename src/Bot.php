<?php
// src/Bot.php
namespace AfghanCodeAI;

/**
 * =================================================================
 * AfghanCodeAI - The Bot's Brain (Final Corrected Version)
 * =================================================================
 * This is the master orchestrator, handling all logic and delegating tasks.
 */
class Bot
{
    private TelegramService $telegram;
    private GeminiClient $gemini;
    private ChatHistory $history;
    private LoggerService $logger;

    public function __construct(TelegramService $telegram, GeminiClient $gemini, ChatHistory $history, LoggerService $logger)
    {
        $this->telegram = $telegram;
        $this->gemini = $gemini;
        $this->history = $history;
        $this->logger = $logger;
    }

    public function handleUpdate(): void
    {
        $update = json_decode(file_get_contents('php://input'), true);

        if (isset($update['message']['text']) && isset($update['message']['from']) && isset($update['message']['chat'])) {
            $message = $update['message'];
            
            $chat_id = $message['chat']['id']; 
            $user_id = $message['from']['id'];
            $user_message = $message['text'];
            
            $isGroupChat = $chat_id < 0;
            $botUsername = str_replace('@', '', BOT_USERNAME); 
            if ($isGroupChat && !str_contains($user_message, '@' . $botUsername)) {
                return; // Silent exit if not mentioned in a group
            }
            
            $user_info = ['id' => $user_id, 'first_name' => $message['from']['first_name'] ?? '', 'username' => $message['from']['username'] ?? 'N/A'];

            if (defined('ADMIN_USER_ID') && $user_id === ADMIN_USER_ID && str_starts_with($user_message, 'دستور عمومی:')) {
                $this->handleAdminCommand($user_message, $chat_id, $message['message_id']);
                return;
            }

            $this->processMessage($chat_id, $user_message, $message['message_id'], $user_info);
        }
    }
    
    private function handleAdminCommand(string $raw_command, int $chat_id, int $message_id): void
    {
        $instruction = trim(str_replace('دستور عمومی:', '', $raw_command));
        $responseText = "⚠️ دستور عمومی نمی‌تواند خالی باشد.";

        if (!empty($instruction)) {
            $this->history->saveGlobalSetting($instruction);
            $responseText = "✅ دستور عمومی با موفقیت در دیتابیس ثبت شد.";
            $this->logger->logSystem("Admin command executed: {$instruction}", "ADMIN");
        }
        
        $this->telegram->sendMessage($responseText, $chat_id, $message_id);
    }

    private function processMessage(int $chat_id, string $user_message, int $message_id, array $user_info): void
    {
        try {
            // --- THE FIX IS HERE: Assembling the full context array correctly ---
            
            // 1. Load the base prompt from the template file
            $base_prompt = json_decode(file_get_contents(PROMPT_TEMPLATE_PATH), true)['contents'];
            
            // 2. Load global rules from the database
            $global_settings_raw = $this->history->loadGlobalSettings();
            $global_settings_context = [];
            foreach ($global_settings_raw as $rule) {
                $global_settings_context[] = ['role' => 'user', 'parts' => [['text' => "قانون عمومی و همیشگی: " . $rule]]];
                $global_settings_context[] = ['role' => 'model', 'parts' => [['text' => "قانون عمومی دریافت شد و اجرا می‌شود."]]];
            }
            
            // 3. Load the personal/group history from the database
            $personal_history = $this->history->load($chat_id);
            
            // 4. Format the current user's prompt
            $current_prompt_string = "[User: {$user_info['first_name']} (Username: @{$user_info['username']}, ID: {$user_info['id']})] says:\n{$user_message}";
            $current_prompt_array = [['role' => 'user', 'parts' => [['text' => $current_prompt_string]]]];
            
            // 5. Merge everything into a single, complete context array
            $full_context = array_merge($base_prompt, $global_settings_context, $personal_history, $current_prompt_array);
            
            // 6. Call Gemini with the single, correct array argument
            $geminiResponse = $this->gemini->getGeminiResponse($full_context);

            if ($geminiResponse['type'] === 'function_call') {
                $this->handleFunctionCall($geminiResponse['data'], $chat_id, $message_id);
            } else {
                $ai_text = $geminiResponse['data'];
                $this->logger->logChat($user_info, $user_message, $ai_text);
                // Pass the original user message string for saving, not the full context
                $this->handleTextResponse($ai_text, $current_prompt_string, $chat_id, $message_id);
            }
        } catch (\Throwable $e) {
            $logMessage = "FATAL PROCESSING ERROR in chat {$chat_id}\nError: {$e->getMessage()}\nTrace: {$e->getTraceAsString()}";
            $this->logger->logSystem($logMessage, 'FATAL');
            $this->telegram->sendMessage("<b>متاسفم، یک خطای داخلی پیش اومد!</b>", $chat_id, $message_id);
        }
    }

    private function handleTextResponse(string $ai_text, string $user_prompt, int $chat_id, int $message_id): void
    {
        try {
            if (trim($ai_text) !== '/warn') {
                $this->history->save($user_prompt, $ai_text, $chat_id);
            }
            $this->telegram->sendMessage($ai_text, $chat_id, $message_id, 'HTML');
        } catch (\Exception $e) {
            $logMessage = "HTML SEND FAILED for chat {$chat_id}\nError: {$e->getMessage()}\nProblematic Text: [{$ai_text}]";
            $this->logger->logSystem($logMessage, 'ERROR');
            $this->telegram->sendMessage(strip_tags($ai_text), $chat_id, $message_id, null);
        }
    }

    private function handleFunctionCall(array $functionCallData, int $chat_id, int $message_id): void
    {
        switch ($functionCallData['name']) {
            case 'send_private_message':
                $this->executeSendPrivateMessage($functionCallData['args'], $chat_id, $message_id);
                break;
            case 'delete_chat_history':
                $this->executeDeleteChatHistory($chat_id, $message_id);
                break;
            default:
                $this->telegram->sendMessage("⚠️ خطا: ابزار ناشناخته‌ای درخواست شد.", $chat_id, $message_id);
                $this->logger->logSystem("Unknown function call requested: {$functionCallData['name']}", "WARNING");
        }
    }
    
    private function executeSendPrivateMessage(array $args, int $original_chat_id, int $original_message_id): void
    {
        $targetUserId = $args['user_id_to_send'] ?? null;
        $messageText = $args['message_text'] ?? null;

        if ($targetUserId && $messageText) {
            try {
                $this->telegram->sendMessage($messageText, (int)$targetUserId);
                $this->telegram->sendMessage("✅ پیام شما با موفقیت برای کاربر <b>{$targetUserId}</b> ارسال شد.", $original_chat_id, $original_message_id);
                $this->logger->logSystem("Executed 'send_private_message' to user {$targetUserId}", "INFO");
            } catch (\Throwable $e) {
                $this->telegram->sendMessage("⚠️ مشکلی پیش اومد. من سعی کردم پیام رو بفرستم ولی نشد.", $original_chat_id, $original_message_id);
                $this->logger->logSystem("Failed to execute 'send_private_message': " . $e->getMessage(), "ERROR");
            }
        } else {
            $this->telegram->sendMessage("⚠️ برای ارسال پیام، باید آیدی عددی و متن پیام رو مشخص کنی.", $original_chat_id, $original_message_id);
        }
    }
    
    private function executeDeleteChatHistory(int $chat_id, int $user_command_message_id): void
    {
        if ($this->history->archive($chat_id)) {
            $confirmationText = "✅ درخواست شما انجام شد. تمام سابقه گفتگوی ما پاک شد و من دیگه بهش دسترسی ندارم.";
            $this->telegram->sendMessage($confirmationText, $chat_id);
            $this->telegram->deleteMessage($chat_id, $user_command_message_id);
            $this->logger->logSystem("Chat history for chat_id {$chat_id} was successfully archived.", "INFO");
        } else {
            $this->telegram->sendMessage("⚠️ مشکلی در آرشیو کردن سابقه گفتگو پیش آمد.", $chat_id, $user_command_message_id);
            $this->logger->logSystem("Failed to archive history for chat_id {$chat_id}.", "ERROR");
        }
    }
}
