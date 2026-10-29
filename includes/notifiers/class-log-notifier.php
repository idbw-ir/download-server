<?php
namespace DownloadServer\Notifier;

if (!defined('ABSPATH')) exit;

class LogNotifier implements NotifierInterface
{
    private string $logFile;
    private bool $enabled;

    public function __construct(bool $enabled = true)
    {
        $this->logFile = WP_CONTENT_DIR . '/download_upload_log.txt';
        $this->enabled = $enabled;
    }

    public function getName(): string { return 'لاگ فایل'; }
    public function isEnabled(): bool { return $this->enabled; }

    public function success(string $message, array $context = []): bool
    {
        return $this->notify('✅ موفقیت', $message, $context);
    }

    public function error(string $message, array $context = []): bool
    {
        return $this->notify('❌ خطا', $message, $context);
    }

    public function notify(string $level, string $message, array $context = []): bool
    {
        if (!$this->isEnabled()) return false;

        $timestamp = date('Y-m-d H:i:s');
        $ctx = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = "[{$timestamp}] [{$level}] {$message}{$ctx}" . PHP_EOL;

        return (bool) @file_put_contents($this->logFile, $line, FILE_APPEND);
    }

    public function getContents(): string
    {
        return file_exists($this->logFile) ? file_get_contents($this->logFile) : '';
    }

    public function clear(): void
    {
        @file_put_contents($this->logFile, '');
    }

    public function delete(): void
    {
        if (file_exists($this->logFile)) @unlink($this->logFile);
    }
}