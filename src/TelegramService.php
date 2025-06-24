<?php
// src/TelegramService.php
namespace AfghanCodeAI;

class TelegramService
{
    private string $botToken;
    private string $apiUrl;

    public function __construct(string $botToken)
    {
        $this->botToken = $botToken;
        $this->apiUrl = "https://api.telegram.org/bot$botToken";
    }

    public function sendMessage(string $messageText, int $chatID, ?int $replyToMessageId = null, ?string $parseMode = 'HTML'): void
    {
        $this->sendRequest('sendMessage', [
            'chat_id' => $chatID,
            'text' => $messageText,
            'reply_to_message_id' => $replyToMessageId,
            'parse_mode' => $parseMode,
        ]);
    }

    /**
     * NEW: Deletes a message in a chat.
     */
    public function deleteMessage(int $chatId, int $messageId): void
    {
        try {
            $this->sendRequest('deleteMessage', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);
            error_log("INFO: Successfully deleted message {$messageId} from chat {$chatId}.");
        } catch (\Throwable $e) {
            // It's okay if deletion fails (e.g., message too old, no permission). We just log it.
            error_log("WARNING: Could not delete message {$messageId} from chat {$chatId}. Reason: " . $e->getMessage());
        }
    }
    
    /**
     * A generic method to send requests to the Telegram API.
     */
    private function sendRequest(string $method, array $params): array
    {
        $url = "{$this->apiUrl}/{$method}";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new \Exception("Telegram API error for method {$method}. Code: {$http_code}, Response: {$response}");
        }

        return json_decode($response, true);
    }
}
