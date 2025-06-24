<?php
// src/ChatHistory.php
namespace AfghanCodeAI;

/**
 * Manages conversation history using a PostgreSQL database.
 * It reads connection details from the centralized config file.
 */
class ChatHistory
{
    private ?\PDO $pdo = null;
    private int $maxLines;

    public function __construct(int $maxLines)
    {
        $this->maxLines = $maxLines;
        $this->connect();
        if ($this->pdo) {
            $this->ensureTableExists();
        }
    }

    private function connect(): void
    {
        try {
            // UPDATED: Uses the new, individual database constants from config.php
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_USER,
                DB_PASS
            );
            $this->pdo = new \PDO($dsn);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            error_log("Failed to connect to PostgreSQL: " . $e->getMessage());
            $this->pdo = null;
        }
    }

    private function ensureTableExists(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS chat_history (
                id SERIAL PRIMARY KEY,
                chat_id BIGINT NOT NULL,
                role VARCHAR(10) NOT NULL,
                content TEXT NOT NULL,
                created_at TIMESTAMPTZ DEFAULT NOW()
            );
            CREATE INDEX IF NOT EXISTS idx_chat_id_timestamp ON chat_history (chat_id, created_at);
        ";
        $this->pdo->exec($sql);
    }

    public function load(int $chatId): array
    {
        if (!$this->pdo) return [];

        $sql = "SELECT role, content FROM chat_history WHERE chat_id = ? ORDER BY created_at ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$chatId]);

        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($row) => ['role' => $row['role'], 'parts' => [['text' => $row['content']]]], $results);
    }

    public function save(string $user_message_with_context, string $ai_response, int $chatId): void
    {
        if (!$this->pdo) return;

        $sql = "INSERT INTO chat_history (chat_id, role, content) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        
        $stmt->execute([$chatId, 'user', $user_message_with_context]);
        $stmt->execute([$chatId, 'model', $ai_response]);
        
        if (defined('UNLIMITED_HISTORY') && !UNLIMITED_HISTORY) {
            $this->trimHistory($chatId);
        }
    }

    private function trimHistory(int $chatId): void
    {
        $countSql = "SELECT COUNT(*) FROM chat_history WHERE chat_id = ?";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute([$chatId]);
        $count = (int)$stmt->fetchColumn();

        if ($count > $this->maxLines) {
            $limit = $count - $this->maxLines;
            $deleteSql = "DELETE FROM chat_history WHERE id IN (SELECT id FROM chat_history WHERE chat_id = ? ORDER BY created_at ASC LIMIT ?)";
            $deleteStmt = $this->pdo->prepare($deleteSql);
            $deleteStmt->execute([$chatId, $limit]);
        }
    }
    
    public function archive(int $chatId): bool
    {
        if (!$this->pdo) return false;
        
        $sql = "DELETE FROM chat_history WHERE chat_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$chatId]);
    }
}
