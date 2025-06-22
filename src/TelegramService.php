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

    /**
     * Sends a message using the Telegram Bot API.
     * Note: This function does NOT escape HTML characters in $messageText.
     * It trusts that the input is already correctly formatted by the AI
     * as per Telegram's HTML styling rules.
     */
    public function sendMessage(string $messageText, int $chatID, ?int $replyToMessageId = null, ?string $parseMode = null): void
    {
        $url = "{$this->apiUrl}/sendMessage";
        $queryParams = [
            'chat_id' => $chatID,
            'text' => $messageText,
        ];
        if ($replyToMessageId !== null) {
            $queryParams['reply_to_message_id'] = $replyToMessageId;
        }
        if ($parseMode !== null) {
            $queryParams['parse_mode'] = $parseMode;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $queryParams);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            // Log detailed error from the Telegram API for debugging
            error_log("--- TELEGRAM API ERROR ---: HTTP Code: $http_code | Response: $response | Parse Mode: $parseMode");
        } else {
            error_log("INFO: Message sent to Telegram successfully. ChatID: $chatID");
        }
    }
}
