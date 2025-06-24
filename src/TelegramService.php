<?php
// src/TelegramService.php
namespace AfghanCodeAI;

/**
 * A simple and direct messenger for the Telegram API.
 * Its only job is to send requests. It throws exceptions on failure,
 * allowing the calling class (Bot) to handle errors and retry logic.
 */
class TelegramService
{
    private string $apiUrl;

    public function __construct(string $botToken)
    {
        $this->apiUrl = "https://api.telegram.org/bot$botToken";
    }

    /**
     * Attempts to send a message.
     * Throws an exception on failure.
     */
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
     * Attempts to delete a message.
     * Logs a warning if it fails, but does not throw an exception to halt execution.
     */
    public function deleteMessage(int $chatId, int $messageId): void
    {
        try {
            $this->sendRequest('deleteMessage', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);
        } catch (\Throwable $e) {
            // This is a non-critical failure. We just log it.
            error_log("WARNING: Could not delete message {$messageId}. Reason: " . $e->getMessage());
        }
    }
    
    /**
     * A generic, private method to send requests to the Telegram API.
     * @throws \Exception on any cURL or API error.
     */
    private function sendRequest(string $method, array $params): array
    {
        $url = "{$this->apiUrl}/{$method}";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200 || $response === false) {
            // Throws a detailed exception that the Bot class will catch and log.
            throw new \Exception("Telegram API error for method {$method}. Code: {$http_code}, Response: " . ($response ?: 'No response'));
        }

        return json_decode($response, true) ?? [];
    }
}
