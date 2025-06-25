<?php
// src/TelegramService.php
namespace AfghanCodeAI;

/**
 * =================================================================
 * AfghanCodeAI - Telegram Messenger Service
 * =================================================================
 * A simple and direct messenger for the Telegram API. Its only job is
 * to send requests. It throws exceptions on failure, letting the Bot
 * class handle errors and retry logic.
 */
class TelegramService
{
    private string $apiUrl;

    public function __construct(string $botToken)
    {
        $this->apiUrl = "https://api.telegram.org/bot$botToken";
    }

    public function sendMessage(string $messageText, int $chatID, ?int $replyToMessageId = null, ?string $parseMode = 'HTML'): void
    {
        $this->sendRequest('sendMessage', ['chat_id' => $chatID, 'text' => $messageText, 'reply_to_message_id' => $replyToMessageId, 'parse_mode' => $parseMode]);
    }

    public function deleteMessage(int $chatId, int $messageId): void
    {
        try {
            $this->sendRequest('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
        } catch (\Throwable $e) {
            error_log("WARNING: Could not delete message {$messageId}. Reason: " . $e->getMessage());
        }
    }

    public function sendDocument(int $chatId, string $filePath, string $caption = ''): void
    {
        $this->sendRequest('sendDocument', ['chat_id' => $chatId, 'caption' => $caption, 'document' => new \CURLFile($filePath)], false);
    }
    
    private function sendRequest(string $method, array $params, bool $isJson = true): array
    {
        $url = "{$this->apiUrl}/{$method}";
        $ch = curl_init($url);
        
        $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30];
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $isJson ? json_encode($params) : $params;
        if($isJson) {
            $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
        }
        
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200 || $response === false) {
            throw new \Exception("Telegram API error for {$method}. Code: {$http_code}, Response: " . ($response ?: 'No response'));
        }

        return json_decode($response, true) ?? [];
    }
}
