<?php
// src/ChatHistory.php

namespace AfghanCodeAI;

class ChatHistory
{
    private string $historyDir;
    private int $maxLines;

    public function __construct(string $historyDir, int $maxLines)
    {
        $this->historyDir = $historyDir;
        $this->maxLines = $maxLines;
        if (!is_dir($this->historyDir)) {
            mkdir($this->historyDir, 0755, true);
        }
    }

    private function getHistoryFilePath(int $userId): string
    {
        return "{$this->historyDir}/chat_{$userId}.log";
    }

    public function load(int $userId): array
    {
        $filePath = $this->getHistoryFilePath($userId);
        if (!file_exists($filePath)) return [];
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return $lines ? array_filter(array_map(fn($line) => json_decode($line, true), $lines)) : [];
    }

    public function save(string $user_message, string $ai_response, int $userId): void
    {
        $filePath = $this->getHistoryFilePath($userId);
        $all_lines = file_exists($filePath) ? file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        
        $all_lines[] = json_encode(['role' => 'user', 'parts' => [['text' => $user_message]]]);
        $all_lines[] = json_encode(['role' => 'model', 'parts' => [['text' => $ai_response]]]);

        // --- NEW: Check for unlimited history flag ---
        if (count($all_lines) > $this->maxLines && !UNLIMITED_HISTORY) {
            $all_lines = array_slice($all_lines, -$this->maxLines);
        }

        file_put_contents($filePath, implode(PHP_EOL, $all_lines) . PHP_EOL, LOCK_EX);
    }
}
