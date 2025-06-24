<?php
// src/LoggerService.php
namespace AfghanCodeAI;

/**
 * A dedicated service for sending logs to specific Telegram channels.
 * It uses its own simple cURL function to avoid dependency loops with TelegramService.
 */
class LoggerService
{
    private string $botToken;
    private string $adminChannelId;
    private string $userChannelId;
    private string $allChannelId;
    private string $systemChannelId;

    public function __construct(string $botToken, string $adminChannelId, string $userChannelId, string $allChannelId, string $systemChannelId)
    {
        $this->botToken = $botToken;
        $this->adminChannelId = $adminChannelId;
        $this->userChannelId = $userChannelId;
        $this->allChannelId = $allChannelId;
        $this->systemChannelId = $systemChannelId;
    }

    /**
     * Logs system-level messages (errors, info, warnings) to the system channel.
     */
    public function logSystem(string $message, string $level = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s T');
        $formattedMessage = "<b>[{$level}]</b>\n";
        $formattedMessage .= "<code>{$timestamp}</code>\n\n";
        $formattedMessage .= "<pre>" . htmlspecialchars($message) . "</pre>";

        $this->sendToChannel($this->systemChannelId, $formattedMessage);
    }

    /**
     * Logs a user-bot conversation turn to the appropriate channels.
     */
    public function logChat(array $userInfo, string $userMessage, string $botResponse): void
    {
        $logData = [
            'timestamp' => date('c'),
            'user' => [
                'id' => $userInfo['id'],
                'first_name' => $userInfo['first_name'],
                'username' => $userInfo['username']
            ],
            'conversation' => [
                'user_says' => $userMessage,
                'bot_responds' => $botResponse
            ]
        ];

        $jsonLog = json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $formattedMessage = "<code>" . htmlspecialchars($jsonLog) . "</code>";

        // Send to the "all chats" log channel
        $this->sendToChannel($this->allChannelId, $formattedMessage);

        // Send to the admin's log channel if the user is the admin
        if ($userInfo['id'] == ADMIN_USER_ID) {
            $this->sendToChannel($this->adminChannelId, $formattedMessage);
        }

        // Send to the specific user's log channel if the user is the one being monitored
        if (defined('MONITORED_USER_ID') && $userInfo['id'] == MONITORED_USER_ID) {
            $this->sendToChannel($this->userChannelId, $formattedMessage);
        }
    }

    /**
     * A simple, self-contained cURL function to send log messages.
     * Fire-and-forget approach with short timeouts.
     */
    private function sendToChannel(string $channelId, string $message): void
    {
        if (empty($channelId) || str_contains($channelId, 'YOUR_')) {
            return; // Don't send if channel ID is not set
        }

        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $params = [
            'chat_id' => $channelId,
            'text' => $message,
            'parse_mode' => 'HTML',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5, // Short timeout for logging
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
