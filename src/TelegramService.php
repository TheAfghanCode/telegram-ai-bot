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
        // --- NEW: Bulletproof HTML Sanitizer ---
        // Before sending, we validate the HTML. If it's broken, we strip all tags to ensure delivery.
        $sanitizedText = $this->validateAndSanitizeHtml($messageText);

        try {
            $this->sendRequest('sendMessage', [
                'chat_id' => $chatID,
                'text' => $sanitizedText, // Use the sanitized text
                'reply_to_message_id' => $replyToMessageId,
                'parse_mode' => $parseMode,
            ]);
        } catch (\Exception $e) {
            // If it still fails, try sending with tags stripped completely as a last resort.
            error_log("HTML Send Failed, Retrying with stripped text. Original Error: " . $e->getMessage());
            $this->sendRequest('sendMessage', [
                'chat_id' => $chatID,
                'text' => strip_tags($messageText), // Fallback to plain text
                'reply_to_message_id' => $replyToMessageId,
                'parse_mode' => null, // No parse mode for plain text
            ]);
        }
    }

    /**
     * Validates if a string contains well-formed HTML for basic tags.
     * If not, it returns the string with all tags stripped to prevent API errors.
     */
    private function validateAndSanitizeHtml(string $html): string
    {
        if (empty(trim($html)) || !str_contains($html, '<')) {
            return $html; // Not HTML, return as is.
        }

        // Use PHP's built-in DOM parser to check for well-formedness.
        // We suppress errors because we are handling them manually.
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        // We wrap the HTML fragment in a div and use a specific encoding to handle UTF-8 correctly.
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="wrapper">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if (!empty($errors)) {
            // There are parsing errors (like unclosed tags).
            error_log("HTML Validation Failed: Malformed HTML detected. Stripping tags. Original text: " . $html);
            return strip_tags($html); // Return plain text as a safe fallback.
        }

        // HTML seems to be well-formed.
        return $html;
    }
    
    // deleteMessage and sendRequest methods remain the same as before...
    public function deleteMessage(int $chatId, int $messageId): void { /* ... */ }
    private function sendRequest(string $method, array $params): array { /* ... */ }
}
