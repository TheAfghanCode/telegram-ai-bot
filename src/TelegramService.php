<?php
// src/TelegramService.php
namespace AfghanCodeAI;

class TelegramService
{
    private string $apiUrl;

    public function __construct(string $botToken)
    {
        $this->apiUrl = "https://api.telegram.org/bot$botToken";
    }

    public function sendMessage(string $messageText, int $chatID, ?int $replyToMessageId = null, ?string $parseMode = 'HTML'): void
    {
        $sanitizedText = $this->validateAndSanitizeHtml($messageText);
        try {
            $this->sendRequest('sendMessage', [
                'chat_id' => $chatID,
                'text' => $sanitizedText,
                'reply_to_message_id' => $replyToMessageId,
                'parse_mode' => $parseMode,
            ]);
        } catch (\Exception $e) {
            error_log("HTML Send Failed, Retrying with stripped text. Original Error: " . $e->getMessage());
            try {
                // Fallback to sending as plain text if HTML parsing fails.
                $this->sendRequest('sendMessage', [
                    'chat_id' => $chatID,
                    'text' => strip_tags($messageText),
                    'reply_to_message_id' => $replyToMessageId,
                    'parse_mode' => null, // No parse mode for plain text
                ]);
            } catch (\Exception $fallback_e) {
                // If even the fallback fails, log it.
                error_log("FATAL SEND ERROR: Fallback plain text send also failed. Error: " . $fallback_e->getMessage());
            }
        }
    }

    public function deleteMessage(int $chatId, int $messageId): void
    {
        try {
            $this->sendRequest('deleteMessage', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);
        } catch (\Throwable $e) {
            error_log("WARNING: Could not delete message {$messageId}. Reason: " . $e->getMessage());
        }
    }

    private function validateAndSanitizeHtml(string $html): string
    {
        if (empty(trim($html)) || !str_contains($html, '<')) {
            return $html;
        }
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors(false);
        if (!empty($errors)) {
            error_log("HTML Validation Failed. Stripping tags. Original: " . $html);
            return strip_tags($html);
        }
        return $html;
    }
    
    /**
     * A generic method to send requests to the Telegram API.
     * THE FIX IS HERE: This function now correctly handles all return paths.
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
            throw new \Exception("Telegram API error for {$method}. Code: {$http_code}, Response: " . ($response ?: 'No response'));
        }

        // json_decode can return null, but the function must return an array.
        // So we cast null to an empty array.
        return json_decode($response, true) ?? [];
    }
}
