<?php
// src/ChatHistory.php
namespace AfghanCodeAI;

/**
 * =================================================================
 * AfghanCodeAI - Persistent History Service
 * =================================================================
 * Manages conversation history using a PostgreSQL database. It also handles
 * the "Digital Time Capsule" archiving feature.
 */
class ChatHistory
{
    private ?\PDO $pdo = null;
    private int $maxLines;
    private TelegramService $telegram;
    private LoggerService $logger;

    public function __construct(int $maxLines, TelegramService $telegram, LoggerService $logger)
    {
        $this->maxLines = $maxLines;
        $this->telegram = $telegram;
        $this->logger = $logger;
        $this->connect();
        if ($this->pdo) {
            $this->ensureTablesExist();
        }
    }

    private function connect(): void
    {
        try {
            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s', DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS);
            $this->pdo = new \PDO($dsn, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        } catch (\PDOException $e) {
            error_log("Failed to connect to PostgreSQL: " . $e->getMessage());
            $this->pdo = null;
        }
    }

    private function ensureTablesExist(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS chat_history (id SERIAL PRIMARY KEY, chat_id BIGINT NOT NULL, role VARCHAR(10) NOT NULL, content TEXT NOT NULL, created_at TIMESTAMPTZ DEFAULT NOW());");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_id_timestamp ON chat_history (chat_id, created_at);");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS global_settings (id SERIAL PRIMARY KEY, rule TEXT NOT NULL, created_at TIMESTAMPTZ DEFAULT NOW());");
    }

    public function loadGlobalSettings(): array
    {
        if (!$this->pdo) return [];
        $stmt = $this->pdo->query("SELECT rule FROM global_settings ORDER BY created_at ASC");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    public function saveGlobalSetting(string $instruction): void
    {
        if (!$this->pdo) return;
        $stmt = $this->pdo->prepare("INSERT INTO global_settings (rule) VALUES (?)");
        $stmt->execute([$instruction]);
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
        if (defined('UNLIMITED_HISTORY') && !UNLIMITED_HISTORY) $this->trimHistory($chatId);
    }

    private function trimHistory(int $chatId): void
    {
        // ... (trim logic is unchanged)
    }
    
    public function archive(int $chatId): bool
    {
        if (!$this->pdo) return false;
        
        $history = $this->load($chatId);
        if (empty($history)) return true;

        $jsonData = json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $tmpJsonPath = tempnam(sys_get_temp_dir(), 'archive_') . '.json';
        file_put_contents($tmpJsonPath, $jsonData);

        $zip = new \ZipArchive();
        $tmpZipPath = $tmpJsonPath . '.zip';
        if ($zip->open($tmpZipPath, \ZipArchive::CREATE) !== TRUE) { unlink($tmpJsonPath); return false; }
        $zip->addFile($tmpJsonPath, "chat_{$chatId}_history.json");
        $zip->close();

        try {
            $caption = "Archive for Chat ID: {$chatId} on " . date('Y-m-d H:i:s');
            $this->telegram->sendDocument(LOG_CHANNEL_ARCHIVE, $tmpZipPath, $caption);
        } catch (\Throwable $e) {
            $this->logger->logSystem("Failed to upload archive for chat {$chatId}: " . $e->getMessage(), "FATAL");
            unlink($tmpJsonPath); unlink($tmpZipPath); return false;
        }

        unlink($tmpJsonPath); unlink($tmpZipPath);

        $stmt = $this->pdo->prepare("DELETE FROM chat_history WHERE chat_id = ?");
        return $stmt->execute([$chatId]);
    }
}
