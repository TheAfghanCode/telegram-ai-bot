<?php
// backup_manager.php

/**
 * =================================================================
 * AfghanCodeAI - Disaster Recovery & Backup Manager
 * =================================================================
 * This script provides a secure way to download a complete backup
 * of the bot's memory from the PostgreSQL database.
 * * --- HOW TO USE ---
 * 1. Place this file in the root directory of your project.
 * 2. Set a strong, secret key in the 'BACKUP_SECRET_KEY' constant below.
 * 3. Access this script via your browser with the secret key as a query parameter:
 * https://your-service.onrender.com/backup_manager.php?secret=YOUR_SECRET_KEY_HERE
 *
 * --- WHAT IT DOES ---
 * 1. Verifies the secret key.
 * 2. Dumps the 'chat_history' and 'global_settings' tables from PostgreSQL.
 * 3. Organizes the data into structured JSON files for easy restoration.
 * 4. Compresses everything into a single, timestamped .zip file.
 * 5. Forces the browser to download the .zip file.
 * 6. Cleans up all temporary files from the server.
 */

// --- SECURITY GATEKEEPER ---
// IMPORTANT: Change this to a long, random, and secret string!
define('BACKUP_SECRET_KEY', 'abcdefghijklmnopqrstuvwxyz');

if (!isset($_GET['secret']) || $_GET['secret'] !== BACKUP_SECRET_KEY) {
    header('HTTP/1.1 403 Forbidden');
    die('Access Denied.');
}

// --- INITIALIZATION ---
// Give the script enough time to process potentially large databases.
set_time_limit(300);

// Load the application configuration to get database credentials.
require_once __DIR__ . '/config.php';

// Check if the ZipArchive class exists.
if (!class_exists('ZipArchive')) {
    die('Error: ZipArchive class is not installed on this server. Please enable the PHP zip extension in your Dockerfile.');
}

$pdo = null;
try {
    // Establish a connection to the PostgreSQL database.
    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s', DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS);
    $pdo = new \PDO($dsn, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
} catch (\PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    die("Database Connection Failed: " . $e->getMessage());
}

// --- BACKUP PROCESS ---

// Create a temporary directory to store data before zipping.
$backupDir = sys_get_temp_dir() . '/afghan_code_ai_backup_' . time();
if (!mkdir($backupDir, 0700, true)) {
    die('Failed to create temporary backup directory.');
}

// 1. Backup Global Settings
$globalSettingsStmt = $pdo->query("SELECT rule, created_at FROM global_settings ORDER BY id ASC");
$globalSettingsData = $globalSettingsStmt->fetchAll(\PDO::FETCH_ASSOC);
file_put_contents($backupDir . '/global_settings.json', json_encode($globalSettingsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 2. Backup Chat History
$chatHistoryStmt = $pdo->query("SELECT chat_id, role, content, created_at FROM chat_history ORDER BY chat_id ASC, created_at ASC");
$chatHistoryData = [];
while ($row = $chatHistoryStmt->fetch(\PDO::FETCH_ASSOC)) {
    $chat_id = $row['chat_id'];
    if (!isset($chatHistoryData[$chat_id])) {
        $chatHistoryData[$chat_id] = [];
    }
    $chatHistoryData[$chat_id][] = [
        'role' => $row['role'],
        'content' => $row['content'],
        'created_at' => $row['created_at'],
    ];
}
file_put_contents($backupDir . '/chat_histories.json', json_encode($chatHistoryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 3. Create Zip Archive
$zipFileName = 'AfghanCodeAI_DB_Backup_' . date('Y-m-d_H-i-s') . '.zip';
$zipFilePath = sys_get_temp_dir() . '/' . $zipFileName;
$zip = new ZipArchive();

if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die('Error: Could not create the zip file.');
}

$zip->addFile($backupDir . '/global_settings.json', 'global_settings.json');
$zip->addFile($backupDir . '/chat_histories.json', 'chat_histories.json');
$zip->addFromString('info.txt', 'Backup created on: ' . date('Y-m-d H:i:s T'));
$zip->close();

// --- DOWNLOAD & CLEANUP ---
if (file_exists($zipFilePath)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($zipFileName) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($zipFilePath));
    
    readfile($zipFilePath);
    
    // Clean up all temporary files and the directory.
    unlink($backupDir . '/global_settings.json');
    unlink($backupDir . '/chat_histories.json');
    rmdir($backupDir);
    unlink($zipFilePath);
    
    exit;
} else {
    die('Error: Could not find the created zip file.');
}
