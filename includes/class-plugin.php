<?php
namespace DownloadServer;

class Plugin
{
    private Settings $settings;
    private Logger $logger;
    private Storage\StorageInterface $storage;
    private Admin $admin;
    private Ajax $ajax;
    private MediaHandler $media;
    private Hooks $hooks;

    public function boot(): void
    {
        // 1. راه‌اندازی اجزا
        $this->settings = new Settings();
        $this->logger   = new Logger();
        $this->storage  = new Storage\HttpStorage($this->settings, $this->logger);
        
        // 2. راه‌اندازی ماژول‌ها
        $this->media = new MediaHandler($this->storage, $this->settings, $this->logger);
        $this->admin = new Admin($this->settings, $this->logger);
        $this->ajax  = new Ajax($this->media, $this->logger);
        $this->hooks = new Hooks($this->settings);

        // 3. ثبت هوک‌ها
        $this->admin->register();
        $this->ajax->register();
        $this->hooks->register();
        $this->media->register();
    }

    // دسترسی به اجزا برای توسعه‌دهندگان
    public static function storage(): Storage\StorageInterface {
        return self::instance()->storage;
    }
    
    public static function logger(): Logger {
        return self::instance()->logger;
    }

    private static $instance;
    private static function instance(): self {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }
}