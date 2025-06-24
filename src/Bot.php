<?php
// src/Bot.php
namespace AfghanCodeAI;

/**
 * The main brain of the bot.
 * It orchestrates all operations: receiving updates, processing messages,
 * handling admin commands, and delegating tasks to other services.
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
            
            $user_info = ['id' => $user_id, 'first_name' => $message['from']['first_name'] ?? '', 'username' => $message['from']['username'] ?? 'N/A'];

            // --- Admin Command Gatekeeper ---
            if (defined('ADMIN_USER_ID') && $user_id === ADMIN_USER_ID && str_starts_with($user_message, 'دستور عمومی:')) {
                $this->handleAdminCommand($user_message, $chat_id, $message['message_id']);
                return; // Stop further processing for admin commands
            }

            // --- Normal Message Processing ---
            $this->processMessage($chat_id, $user_message, $message['message_id'], $user_info);
        }
    }
    
    private function handleAdminCommand(string $raw_command, int $chat_id, int $message_id): void
    {
        $instruction = trim(str_replace('دستور عمومی:', '', $raw_command));
        $responseText = "⚠️ دستور عمومی نمی‌تواند خالی باشد.";

        if (!empty($instruction) && defined('PUBLIC_MEMORY_FILE')) {
            file_put_contents(PUBLIC_MEMORY_FILE, $instruction . PHP_EOL, FILE_APPEND | LOCK_EX);
            $responseText = "✅ دستور عمومی با موفقیت ثبت شد و از این پس برای تمام کاربران اعمال می‌شود.";
            $this->logger->logSystem("Admin command executed: {$instruction}", "ADMIN");
        }
        
        $this->telegram->sendMessage($responseText, $chat_id, $message_id);
    }

    private function processMessage(int $chat_id, string $user_message, int $message_id, array $user_info): void
    {
        try {
            $formatted_prompt = "[User: {$user_info['first_name']} (Username: @{$user_info['username']}, ID: {$user_info['id']})] says:\n{$user_message}";
            $history_contents = $this->history->load($chat_id);
            $geminiResponse = $this->gemini->getGeminiResponse($formatted_prompt, $history_contents);

            if ($geminiResponse['type'] === 'function_call') {
                $this->logger->logSystem("Function call requested: {$geminiResponse['data']['name']}", 'DEBUG');
                $this->handleFunctionCall($geminiResponse['data'], $chat_id, $message_id);
            } else {
                $ai_text = $geminiResponse['data'];
                $this->logger->logChat($user_info, $user_message, $ai_text);
                $this->handleTextResponse($ai_text, $formatted_prompt, $chat_id, $message_id);
            }
        } catch (\Throwable $e) {
            $logMessage = "FATAL PROCESSING ERROR in chat {$chat_id}\nError: {$e->getMessage()}\nTrace: {$e->getTraceAsString()}";
            $this->logger->logSystem($logMessage, 'FATAL');
            $this->telegram->sendMessage("<b>متاسفم، یک خطای داخلی پیش اومد!</b>", $chat_id, $message_id);
        }
    }

    private function handleTextResponse(string $ai_text, string $formatted_prompt, int $chat_id, int $message_id): void
    {
        try {
            if (trim($ai_text) !== '/warn') {
                $this->history->save($formatted_prompt, $ai_text, $chat_id);
            }
            $this->telegram->sendMessage($ai_text, $chat_id, $message_id, 'HTML');
        } catch (\Exception $e) {
            $logMessage = "HTML SEND FAILED for chat {$chat_id}\nError: {$e->getMessage()}\nProblematic Text: [{$ai_text}]";
            $this->logger->logSystem($logMessage, 'ERROR');
            
            // Fallback attempt: send the message with all tags stripped.
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
