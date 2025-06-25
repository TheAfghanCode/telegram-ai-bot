<?php
// src/LoggerService.php
namespace AfghanCodeAI;

/**
 * =================================================================
 * AfghanCodeAI - Central Logger Service
 * =================================================================
 * A dedicated service for sending logs to specific Telegram channels.
 * It's the central nervous system for monitoring the bot's health and activity.
 */
class LoggerService
{
    private string $botToken;
    private string $adminChannelId;
    private string $userChannelId;
    private string $allChannelId;
    private string $systemChannelId;
    private string $archiveChannelId;

    public function __construct(string $botToken, string $adminChannelId, string $userChannelId, string $allChannelId, string $systemChannelId, string $archiveChannelId)
    {
        $this->botToken = $botToken;
        $this->adminChannelId = $adminChannelId;
        $this->userChannelId = $userChannelId;
        $this->allChannelId = $allChannelId;
        $this->systemChannelId = $systemChannelId;
        $this->archiveChannelId = $archiveChannelId;
    }

    public function logSystem(string $message, string $level = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s T');
        $formattedMessage = "<b>[{$level}]</b>\n";
        $formattedMessage .= "<code>{$timestamp}</code>\n\n";
        $formattedMessage .= "<pre>" . htmlspecialchars($message) . "</pre>";

        $this->sendToChannel($this->systemChannelId, $formattedMessage);
    }

    public function logChat(array $userInfo, string $userMessage, string $botResponse): void
    {
        $logData = [
            'timestamp' => date('c'),
            'user' => $userInfo,
            'conversation' => ['user_says' => $userMessage, 'bot_responds' => $botResponse]
        ];

        $jsonLog = json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $formattedMessage = "<code>" . htmlspecialchars($jsonLog) . "</code>";

        $this->sendToChannel($this->allChannelId, $formattedMessage);
        
        if (defined('ADMIN_USER_ID') && $userInfo['id'] == ADMIN_USER_ID) {
            $this->sendToChannel($this->adminChannelId, $formattedMessage);
        }

        if (defined('MONITORED_USER_ID') && MONITORED_USER_ID != 'ID_OF_USER_TO_MONITOR' && $userInfo['id'] == MONITORED_USER_ID) {
            $this->sendToChannel($this->userChannelId, $formattedMessage);
        }
    }

    /**
     * A simple, self-contained cURL function to send log messages.
     * This is separate from TelegramService to avoid circular dependencies or complex error handling for logs.
     */
    private function sendToChannel(string $channelId, string $message): void
    {
        // Do not attempt to send if channel ID is not configured
        if (empty($channelId) || str_contains($channelId, 'YOUR_')) {
            return; 
        }

        // Truncate message if it's too long for a Telegram message
        if (mb_strlen($message, 'UTF-8') > 4096) {
            $message = mb_substr($message, 0, 4000, 'UTF-8') . "\n\n<b>[MESSAGE TRUNCATED]</b>";
        }

        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $params = ['chat_id' => $channelId, 'text' => $message, 'parse_mode' => 'HTML'];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, 
            CURLOPT_POSTFIELDS => $params, 
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5, // Short timeout for non-critical logging
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        curl_exec($ch);
        // We don't care about the response for logging. It's a fire-and-forget operation.
        curl_close($ch);
    }
}
