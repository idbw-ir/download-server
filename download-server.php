<?php
/**
 * Plugin Name: سرور دانلود رسانه
 * Description: آپلود خودکار رسانه‌ها به سرور دانلود با معماری توسعه‌پذیر
 * Version: 1.1
 * Author: حسین عظیمی بیرجندی
 * Text Domain: download-server
 */

if (!defined('ABSPATH')) exit;

// تعریف ثابت‌ها
define('DS_VERSION', '1.1');
define('DS_PATH', plugin_dir_path(__FILE__));
define('DS_URL', plugin_dir_url(__FILE__));
define('DS_BASENAME', plugin_basename(__FILE__));

// Autoloader ساده
spl_autoload_register(function ($class) {
    $prefix = 'DownloadServer\\';
    if (strpos($class, $prefix) !== 0) return;

    $relative = substr($class, strlen($prefix));
    $file = DS_PATH . 'includes/' . str_replace('\\', '/', $relative) . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// اجرای پلاگین
add_action('plugins_loaded', function () {
    $plugin = new DownloadServer\Plugin();
    $plugin->boot();
});