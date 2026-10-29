<?php
namespace DownloadServer\Notifier;

if (!defined('ABSPATH')) exit;

class NotifierManager
{
    /** @var NotifierInterface[] */
    private array $notifiers = [];

    /** ثبت یک Notifier جدید */
    public function register(NotifierInterface $notifier): void
    {
        $this->notifiers[$notifier->getName()] = $notifier;
    }

    /** دریافت لیست Notifierهای ثبت‌شده */
    public function getAll(): array
    {
        return $this->notifiers;
    }

    /** ارسال پیام موفقیت به همه کانال‌های فعال */
    public function success(string $message, array $context = []): void
    {
        foreach ($this->notifiers as $notifier) {
            if ($notifier->isEnabled()) {
                try {
                    $notifier->success($message, $context);
                } catch (\Throwable $e) {
                    error_log('[DownloadServer] Notifier Error: ' . $e->getMessage());
                }
            }
        }
    }

    /** ارسال پیام خطا به همه کانال‌های فعال */
    public function error(string $message, array $context = []): void
    {
        foreach ($this->notifiers as $notifier) {
            if ($notifier->isEnabled()) {
                try {
                    $notifier->error($message, $context);
                } catch (\Throwable $e) {
                    error_log('[DownloadServer] Notifier Error: ' . $e->getMessage());
                }
            }
        }
    }

    /** ارسال پیام عمومی */
    public function notify(string $level, string $message, array $context = []): void
    {
        foreach ($this->notifiers as $notifier) {
            if ($notifier->isEnabled()) {
                try {
                    $notifier->notify($level, $message, $context);
                } catch (\Throwable $e) {
                    error_log('[DownloadServer] Notifier Error: ' . $e->getMessage());
                }
            }
        }
    }
}