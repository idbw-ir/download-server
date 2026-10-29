<?php
namespace DownloadServer;

if (!defined('ABSPATH')) exit;

class Plugin
{
    private Settings $settings;
    private Logger $logger;
    private Storage\StorageInterface $storage;
    private Notifier\NotifierManager $notifiers;
    private Admin $admin;
    private Ajax $ajax;
    private MediaHandler $media;
    private Hooks $hooks;

    private static $instance;

    public function boot(): void
    {
        self::$instance = $this;

        // 1. اجزا پایه
        $this->settings = new Settings();
        $this->logger   = new Logger();
        $this->storage  = new Storage\HttpStorage($this->settings, $this->logger);

        // 2. راه‌اندازی سیستم Notifier
        $this->notifiers = new Notifier\NotifierManager();
        $this->bootNotifiers();

        // 3. ماژول‌ها
        $this->media = new MediaHandler($this->storage, $this->settings, $this->logger, $this->notifiers);
        $this->admin = new Admin($this->settings, $this->logger, $this->notifiers);
        $this->ajax  = new Ajax($this->media, $this->logger);
        $this->hooks = new Hooks($this->settings);

        // 4. ثبت هوک‌ها
        $this->admin->register();
        $this->ajax->register();
        $this->hooks->register();
        $this->media->register();
    }

    /** راه‌اندازی Notifierهای فعال */
    private function bootNotifiers(): void
    {
        // 1. Log Notifier (همیشه فعال - سازگاری با نسخه قبلی)
        $this->notifiers->register(new Notifier\LogNotifier(true));

        // 2. Telegram Notifier (در صورت فعال بودن)
        $tgEnabled  = (bool) get_option('ds_telegram_enabled', false);
        $tgToken    = get_option('ds_telegram_bot_token', '');
        $tgChatId   = get_option('ds_telegram_chat_id', '');

        if ($tgEnabled && !empty($tgToken) && !empty($tgChatId)) {
            $this->notifiers->register(new Notifier\TelegramNotifier($tgToken, $tgChatId));
        }

        // 🔌 هوک برای توسعه‌دهندگان: افزودن Notifier سفارشی
        do_action('download_server_register_notifiers', $this->notifiers);
    }

    // دسترسی‌های استاتیک
    public static function instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function storage(): Storage\StorageInterface
    {
        return self::instance()->storage;
    }

    public static function notifiers(): Notifier\NotifierManager
    {
        return self::instance()->notifiers;
    }

    public static function logger(): Logger
    {
        return self::instance()->logger;
    }

    public static function settings(): Settings
    {
        return self::instance()->settings;
    }
}