<?php
namespace DownloadServer;

class Logger
{
    private string $logFile;

    public function __construct() {
        $this->logFile = WP_CONTENT_DIR . '/download_upload_log.txt';
    }

    public function info(string $message, array $context = []): void {
        $this->write('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void {
        $this->write('ERROR', $message, $context);
    }

    public function getContents(): string {
        return file_exists($this->logFile) ? file_get_contents($this->logFile) : '';
    }

    public function clear(): void {
        file_put_contents($this->logFile, '');
    }

    public function delete(): void {
        if (file_exists($this->logFile)) unlink($this->logFile);
    }

    private function write(string $level, string $message, array $context): void {
        $timestamp = date('Y-m-d H:i:s');
        $ctx = $context ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = "[{$timestamp}] [{$level}] {$message}{$ctx}" . PHP_EOL;
        file_put_contents($this->logFile, $line, FILE_APPEND);
    }
}