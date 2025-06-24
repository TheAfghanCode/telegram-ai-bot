<?php
// backup.php

/**
 * A utility script to securely download a zip archive of all conversation histories.
 * To use, simply navigate to this file in your browser.
 * For security, you might want to add a password check or rename this file to something secret.
 */

// Load the configuration to get directory paths
require_once __DIR__ . '/config.php';

// Define source directories and the temporary zip file path
$historyDir = CHAT_HISTORY_DIR;
$archiveDir = ARCHIVED_HISTORY_DIR;
$zipFileName = 'AfghanCodeAI_Backup_' . date('Y-m-d_H-i-s') . '.zip';
$zipFilePath = sys_get_temp_dir() . '/' . $zipFileName;

// Check if the ZipArchive class exists
if (!class_exists('ZipArchive')) {
    die('Error: ZipArchive class is not installed on this server. Please enable the PHP zip extension.');
}

$zip = new ZipArchive();

if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die('Error: Could not create the zip file.');
}

// Helper function to add a directory's contents to the zip file
function addDirectoryToZip(ZipArchive $zip, string $dirPath, string $zipPath): void
{
    if (!is_dir($dirPath)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
        // Skip directories (they would be added automatically)
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            // Create a relative path inside the zip file
            $relativePath = $zipPath . '/' . substr($filePath, strlen($dirPath) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
}

// Add both history directories to the zip
addDirectoryToZip($zip, $historyDir, 'history');
addDirectoryToZip($zip, $archiveDir, 'archived_history');

// Close the zip file
$zip->close();

// --- Force Download ---
// Make sure the file exists before proceeding
if (file_exists($zipFilePath)) {
    // Set headers to trigger download
    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($zipFileName) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($zipFilePath));
    
    // Clear output buffer and read the file
    flush(); // Flush system output buffer
    readfile($zipFilePath);
    
    // Delete the temporary file from the server after download
    unlink($zipFilePath);
    
    exit;
} else {
    die('Error: Could not find the created zip file.');
}
