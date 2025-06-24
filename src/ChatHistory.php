<?php
// src/ChatHistory.php
namespace AfghanCodeAI;

class ChatHistory
{
    private string $historyDir;
    private string $archiveDir;
    private int $maxLines;

    public function __construct(string $historyDir, string $archiveDir, int $maxLines)
    {
        $this->historyDir = $historyDir;
        $this->archiveDir = $archiveDir;
        $this->maxLines = $maxLines;
        if (!is_dir($this->historyDir)) mkdir($this->historyDir, 0777, true);
        if (!is_dir($this->archiveDir)) mkdir($this->archiveDir, 0777, true);
    }
    
    private function getHistoryFilePath(int $chatId): string
    {
        // Use chat ID as the unique key for the conversation file
        return "{$this->historyDir}/chat_{$chatId}.log";
    }

    public function load(int $chatId): array
    {
        $filePath = $this->getHistoryFilePath($chatId);
        if (!file_exists($filePath)) return [];
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return $lines ? array_filter(array_map(fn($line) => json_decode($line, true), $lines)) : [];
    }

    public function save(string $user_message_with_context, string $ai_response, int $chatId): void
    {
        $filePath = $this->getHistoryFilePath($chatId);
        $all_lines = file_exists($filePath) ? file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        
        $all_lines[] = json_encode(['role' => 'user', 'parts' => [['text' => $user_message_with_context]]]);
        $all_lines[] = json_encode(['role' => 'model', 'parts' => [['text' => $ai_response]]]);

        if (count($all_lines) > $this->maxLines) {
            $all_lines = array_slice($all_lines, -$this->maxLines);
        }

        file_put_contents($filePath, implode(PHP_EOL, $all_lines) . PHP_EOL, LOCK_EX);
    }
    
    /**
     * Moves a chat history file to the archive directory.
     * @return bool True on success, false on failure.
     */
    public function archive(int $chatId): bool
    {
        $sourcePath = $this->getHistoryFilePath($chatId);
        if (!file_exists($sourcePath)) {
            return true; // Nothing to archive, so it's a success.
        }
        
        $archiveFileName = "archived_chat_{$chatId}_" . date('Y-m-d_H-i-s') . ".log";
        $destinationPath = "{$this->archiveDir}/{$archiveFileName}";
        
        return rename($sourcePath, $destinationPath);
    }
}
