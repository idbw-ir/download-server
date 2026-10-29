<?php
namespace DownloadServer\Notifier;

if (!defined('ABSPATH')) exit;

/**
 * قرارداد سیستم اطلاع‌رسانی
 * هر Notifier جدید باید این متدها را پیاده‌سازی کند
 */
interface NotifierInterface
{
    /** نام نمایشی Notifier */
    public function getName(): string;

    /** آیا این Notifier فعال است؟ */
    public function isEnabled(): bool;

    /** ارسال پیام موفقیت */
    public function success(string $message, array $context = []): bool;

    /** ارسال پیام خطا */
    public function error(string $message, array $context = []): bool;

    /** ارسال پیام اطلاع‌رسانی عمومی */
    public function notify(string $level, string $message, array $context = []): bool;
}