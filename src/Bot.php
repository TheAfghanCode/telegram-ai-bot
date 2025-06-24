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
            
            // The unique identifier for a conversation context is the chat ID.
            $chat_id = $message['chat']['id']; 

            // User info is still valuable for context within the prompt.
            $user_info = [
                'id' => $message['from']['id'],
                'first_name' => $message['from']['first_name'] ?? '',
                'username' => $message['from']['username'] ?? 'N/A'
            ];

            $this->processMessage($chat_id, $message['text'], $message['message_id'], $user_info);
        }
    }

    private function processMessage(int $chat_id, string $user_message, int $message_id, array $user_info): void
    {
        try {
            // Create a richer prompt with all user details
            $formatted_prompt = "[User: {$user_info['first_name']} (Username: @{$user_info['username']}, ID: {$user_info['id']})] says:\n{$user_message}";
            
            // The history is now loaded based on the chat ID (group or private)
            $history_contents = $this->history->load($chat_id);
            
            $geminiResponse = $this->gemini->getGeminiResponse($formatted_prompt, $history_contents);

            if ($geminiResponse['type'] === 'function_call') {
                $this->handleFunctionCall($geminiResponse['data'], $chat_id, $message_id);
            } else {
                $this->handleTextResponse($geminiResponse['data'], $formatted_prompt, $chat_id, $message_id);
            }
        } catch (\Throwable $e) {
            error_log("--- PROCESSING ERROR ---: ChatID: $chat_id, UserID: {$user_info['id']}. Error: " . $e->getMessage());
            $this->telegram->sendMessage("<b>متاسفم، یک خطای داخلی پیش اومد!</b>", $chat_id, $message_id);
        }
    }

    private function handleTextResponse(string $ai_text, string $formatted_prompt, int $chat_id, int $message_id): void
    {
        if (trim($ai_text) !== '/warn') {
            $this->history->save($formatted_prompt, $ai_text, $chat_id);
        }
        $this->telegram->sendMessage($ai_text, $chat_id, $message_id);
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
            } catch (\Throwable $e) {
                $this->telegram->sendMessage("⚠️ خطا در ارسال پیام.", $original_chat_id, $original_message_id);
            }
        }
    }
    
    private function executeDeleteChatHistory(int $chat_id, int $user_command_message_id): void
    {
        if ($this->history->archive($chat_id)) {
            $confirmationText = "✅ درخواست شما انجام شد. تمام سابقه گفتگوی ما پاک شد و من دیگه بهش دسترسی ندارم.";
            $this->telegram->sendMessage($confirmationText, $chat_id);
            $this->telegram->deleteMessage($chat_id, $user_command_message_id);
        } else {
            $this->telegram->sendMessage("⚠️ مشکلی در آرشیو کردن سابقه گفتگو پیش آمد.", $chat_id, $user_command_message_id);
        }
    }
}
