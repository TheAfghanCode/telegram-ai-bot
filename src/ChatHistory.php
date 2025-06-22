<?php
// src/ChatHistory.php

namespace AfghanCodeAI;

class ChatHistory
{
    private string $filePath;
    private int $maxLines;

    public function __construct(string $filePath, int $maxLines)
    {
        $this->filePath = $filePath;
        $this->maxLines = $maxLines;
    }

    /**
     * Loads the history from the log file.
     */
    public function load(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }
        $lines = file($this->filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            error_log("WARNING: Could not read chat history file: {$this->filePath}");
            return [];
        }
        
        // Ensure that only valid JSON lines are processed and returned
        return array_filter(array_map(function($line) {
            return json_decode($line, true);
        }, $lines));
    }

    /**
     * Saves a new user message and AI response to the history file.
     */
    public function save(string $user_message, string $ai_response): void
    {
        $user_entry = json_encode(['role' => 'user', 'parts' => [['text' => $user_message]]]);
        $model_entry = json_encode(['role' => 'model', 'parts' => [['text' => $ai_response]]]);

        $all_lines = $this->loadRawLines();
        $all_lines[] = $user_entry;
        $all_lines[] = $model_entry;

        // Trim the history if it exceeds the max line count
        if (count($all_lines) > $this->maxLines) {
            $all_lines = array_slice($all_lines, -$this->maxLines);
        }

        // Use LOCK_EX to prevent race conditions during concurrent writes
        $result = file_put_contents($this->filePath, implode(PHP_EOL, $all_lines) . PHP_EOL, LOCK_EX);
        if ($result === false) {
            error_log("ERROR: Failed to write to chat history file: {$this->filePath}");
        }
    }

    /**
     * Reads raw lines from the file, used internally by the save method.
     */
    private function loadRawLines(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }
        return file($this->filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    }
}
