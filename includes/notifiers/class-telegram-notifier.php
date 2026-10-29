<?php
namespace DownloadServer\Notifier;

if (!defined('ABSPATH')) exit;

class TelegramNotifier implements NotifierInterface
{
    private string $botToken;
    private string $chatId;
    private bool $enabled;

    public function __construct(string $botToken, string $chatId, bool $enabled = true)
    {
        $this->botToken = $botToken;
        $this->chatId   = $chatId;
        $this->enabled  = $enabled && !empty($botToken) && !empty($chatId);
    }

    public function getName(): string { return 'تلگرام'; }
    public function isEnabled(): bool { return $this->enabled; }

    public function success(string $message, array $context = []): bool
    {
        return $this->send('✅ <b>موفقیت</b>', $message, $context);
    }

    public function error(string $message, array $context = []): bool
    {
        return $this->send('❌ <b>خطا</b>', $message, $context);
    }

    public function notify(string $level, string $message, array $context = []): bool
    {
        return $this->send("🔔 <b>{$level}</b>", $message, $context);
    }

    private function send(string $level, string $message, array $context): bool
    {
        if (!$this->isEnabled()) return false;

        $text = "{$level}\n\n" . esc_html($message);

        if (!empty($context)) {
            $text .= "\n\n<b>جزئیات:</b>\n";
            foreach ($context as $key => $value) {
                $val = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
                $text .= "• <code>" . esc_html($key) . ":</code> " . esc_html($val) . "\n";
            }
        }

        $text .= "\n🕐 " . date('Y-m-d H:i:s');

        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $response = wp_remote_post($url, [
            'body' => [
                'chat_id'    => $this->chatId,
                'text'       => $text,
                'parse_mode' => 'HTML',
            ],
            'timeout' => 10,
        ]);

        return !is_wp_error($response);
    }
}