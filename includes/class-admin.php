<?php
namespace DownloadServer;

use DownloadServer\Notifier\NotifierManager;
use DownloadServer\Notifier\LogNotifier;

if (!defined('ABSPATH')) exit;

class Admin
{
    private Settings $settings;
    private Logger $logger;
    private NotifierManager $notifiers;

    public function __construct(Settings $settings, Logger $logger, NotifierManager $notifiers)
    {
        $this->settings  = $settings;
        $this->logger    = $logger;
        $this->notifiers = $notifiers;
    }

    /**
     * ثبت تمام هوک‌های ادمین
     */
    public function register(): void
    {
        // منو و تنظیمات
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_init', [$this, 'registerSettings']);

        // ستون جدید در رسانه‌ها
        add_filter('manage_upload_columns', [$this, 'addColumn']);
        add_action('manage_media_custom_column', [$this, 'renderColumn'], 10, 2);

        // اسکریپت AJAX در صفحه رسانه‌ها
        add_action('admin_footer-upload.php', [$this, 'renderAdminJs']);

        // AJAX handlers
        add_action('wp_ajax_ds_upload_media', [$this, 'handleUpload']);
        add_action('wp_ajax_ds_clear_log', [$this, 'handleClearLog']);
        add_action('wp_ajax_ds_delete_log', [$this, 'handleDeleteLog']);
        add_action('wp_ajax_ds_fix_media_batch', [$this, 'handleFixMediaBatch']);
    }

    /**
     * افزودن منوی تنظیمات
     */
    public function addMenu(): void
    {
        add_options_page(
            'تنظیمات سرور دانلود',
            'سرور دانلود',
            'manage_options',
            'download-media-settings',
            [$this, 'renderSettingsPage']
        );
    }

    /**
     * ثبت تنظیمات
     */
    public function registerSettings(): void
    {
        // تنظیمات اصلی
        $this->settings->register();

        // تنظیمات تلگرام
        register_setting('download_media_settings', 'ds_telegram_enabled');
        register_setting('download_media_settings', 'ds_telegram_bot_token');
        register_setting('download_media_settings', 'ds_telegram_chat_id');
    }

    /**
     * افزودن ستون جدید به لیست رسانه‌ها
     */
    public function addColumn(array $columns): array
    {
        $columns['download_uploaded'] = 'آپلود در سرور دانلود';
        return $columns;
    }

    /**
     * رندر محتوای ستون جدید
     */
    public function renderColumn(string $column, int $postId): void
    {
        if ($column !== 'download_uploaded') return;

        $relative = get_post_meta($postId, '_wp_attached_file', true);
        $uploaded = get_post_meta($postId, '_download_uploaded', true);

        if (!$uploaded || !$relative) {
            echo '<button class="button ds-upload-btn" data-id="' . esc_attr($postId) . '">آپلود به سرور دانلود</button>';
            echo '<span class="ds-upload-msg" id="ds-msg-' . esc_attr($postId) . '"></span>';
            return;
        }

        $url = $this->settings->getDownloadUrl() . '/' . str_replace('\\', '/', $relative);

        // بررسی وجود فایل با استفاده از Storage
        $storage = Plugin::storage();
        if ($storage->exists($relative)) {
            echo '<a href="' . esc_url($url) . '" target="_blank" style="color:green;">✓ آپلود شده</a>';
        } else {
            echo '<button class="button ds-upload-btn" data-id="' . esc_attr($postId) . '">آپلود به سرور دانلود</button>';
            echo '<span class="ds-upload-msg" id="ds-msg-' . esc_attr($postId) . '"></span>';
        }
    }

    /**
     * اسکریپت JavaScript برای تعاملات AJAX
     */
    public function renderAdminJs(): void
    {
        ?>
        <script>
        jQuery(document).ready(function($){
            // آپلود دستی رسانه
            $('.ds-upload-btn').click(function(){
                var btn = $(this);
                var postId = btn.data('id');
                var msg = $('#ds-msg-' + postId);
                
                btn.prop('disabled', true);
                msg.text('در حال آپلود...').css('color', '');
                
                $.post(ajaxurl, {
                    action: 'ds_upload_media',
                    post_id: postId,
                    _ajax_nonce: '<?php echo wp_create_nonce('ds_upload_nonce'); ?>'
                }, function(response){
                    if (response.success) {
                        msg.text('✓ آپلود موفق').css('color', 'green');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        msg.text('✗ خطا: ' + response.data).css('color', 'red');
                        btn.prop('disabled', false);
                    }
                }).fail(() => {
                    msg.text('✗ خطای ارتباط با سرور').css('color', 'red');
                    btn.prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * هندلر AJAX برای آپلود دستی
     */
    public function handleUpload(): void
    {
        check_ajax_referer('ds_upload_nonce');
        
        $postId = intval($_POST['post_id'] ?? 0);
        
        if (!$postId || !current_user_can('upload_files')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }

        $media = Plugin::instance()->media ?? null;
        if (!$media) {
            wp_send_json_error('سیستم آماده نیست');
        }

        if ($media->uploadManual($postId)) {
            $this->notifiers->success('آپلود دستی فایل موفق بود', [
                'post_id' => $postId,
            ]);
            wp_send_json_success();
        }
        
        wp_send_json_error('آپلود ناموفق بود');
    }

    /**
     * هندلر AJAX برای خالی کردن لاگ
     */
    public function handleClearLog(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }

        // پیدا کردن LogNotifier
        foreach ($this->notifiers->getAll() as $notifier) {
            if ($notifier instanceof LogNotifier) {
                $notifier->clear();
                break;
            }
        }

        wp_send_json_success('لاگ با موفقیت خالی شد');
    }

    /**
     * هندلر AJAX برای حذف فایل لاگ
     */
    public function handleDeleteLog(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }

        // پیدا کردن LogNotifier
        foreach ($this->notifiers->getAll() as $notifier) {
            if ($notifier instanceof LogNotifier) {
                $notifier->delete();
                break;
            }
        }

        wp_send_json_success('فایل لاگ با موفقیت حذف شد');
    }

    /**
     * هندلر AJAX برای اصلاح دسته‌ای رسانه‌های قبلی
     */
    public function handleFixMediaBatch(): void
    {
        check_ajax_referer('ds_fix_media_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }

        $batch = 50;
        $attachments = get_posts([
            'post_type'      => 'attachment',
            'posts_per_page' => $batch,
            'meta_query'     => [
                [
                    'key'     => '_download_uploaded',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ]);

        if (empty($attachments)) {
            $this->notifiers->success('اصلاح رسانه‌ها تکمیل شد', [
                'total' => 'همه رسانه‌ها',
            ]);
            wp_send_json_success([
                'done'    => true,
                'message' => '✅ همه رسانه‌ها بررسی و اصلاح شدند'
            ]);
        }

        $processed = 0;
        foreach ($attachments as $att) {
            $file = get_attached_file($att->ID);
            if (!$file || !file_exists($file)) continue;

            $relative = str_replace(
                wp_get_upload_dir()['basedir'] . '/',
                '',
                $file
            );

            update_post_meta($att->ID, '_download_uploaded', 1);
            update_post_meta($att->ID, '_wp_attached_file', $relative);
            $processed++;
        }

        wp_send_json_success([
            'done'    => false,
            'message' => "⏳ در حال اصلاح دسته بعدی... ({$processed} فایل پردازش شد)"
        ]);
    }

    /**
     * رندر صفحه تنظیمات
     */
    public function renderSettingsPage(): void
    {
        // دریافت محتوای لاگ
        $logs = 'هیچ لاگی ثبت نشده است.';
        foreach ($this->notifiers->getAll() as $notifier) {
            if ($notifier instanceof LogNotifier) {
                $content = $notifier->getContents();
                if (!empty($content)) {
                    $logs = htmlspecialchars($content);
                }
                break;
            }
        }
        ?>
        <div class="wrap">
            <h1>تنظیمات سرور دانلود</h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('download_media_settings'); ?>
                
                <h2>⚙️ تنظیمات اصلی</h2>
                <table class="form-table">
                    <tr>
                        <th><label>فعال‌سازی</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?= esc_attr(Settings::OPTION_ENABLED) ?>" value="1" 
                                    <?= checked(get_option(Settings::OPTION_ENABLED, true), true, false); ?>>
                                آپلود خودکار فعال باشد
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="download_url">آدرس فایل‌ها</label></th>
                        <td>
                            <input type="text" name="<?= esc_attr(Settings::OPTION_URL) ?>" id="download_url"
                                   value="<?= esc_attr(get_option(Settings::OPTION_URL)); ?>" 
                                   class="regular-text" placeholder="https://example.com/uploads">
                            <p class="description">آدرس پایه‌ای که فایل‌ها از آن دانلود می‌شوند</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="download_upload_url">آدرس upload.php</label></th>
                        <td>
                            <input type="text" name="<?= esc_attr(Settings::OPTION_UPLOAD_URL) ?>" id="download_upload_url"
                                   value="<?= esc_attr(get_option(Settings::OPTION_UPLOAD_URL)); ?>" 
                                   class="regular-text" placeholder="https://example.com/upload.php">
                            <p class="description">آدرس اسکریپت آپلود در سرور دانلود</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="download_secret_key">کلید مخفی</label></th>
                        <td>
                            <input type="text" name="<?= esc_attr(Settings::OPTION_SECRET_KEY) ?>" id="download_secret_key"
                                   value="<?= esc_attr(get_option(Settings::OPTION_SECRET_KEY)); ?>" 
                                   class="regular-text">
                            <p class="description">کلید امنیتی برای احراز هویت با سرور دانلود</p>
                        </td>
                    </tr>
                </table>

                <hr>
                <h2>🔔 تنظیمات اطلاع‌رسانی تلگرام</h2>
                <table class="form-table">
                    <tr>
                        <th>فعال‌سازی تلگرام</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ds_telegram_enabled" value="1" 
                                    <?= checked(get_option('ds_telegram_enabled', false), true, false); ?>>
                                ارسال اطلاعیه به تلگرام
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ds_telegram_bot_token">Bot Token</label></th>
                        <td>
                            <input type="text" name="ds_telegram_bot_token" id="ds_telegram_bot_token"
                                   value="<?= esc_attr(get_option('ds_telegram_bot_token')); ?>" 
                                   class="regular-text" placeholder="123456:ABC-DEF...">
                            <p class="description">توکن ربات تلگرام خود را وارد کنید</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ds_telegram_chat_id">Chat ID</label></th>
                        <td>
                            <input type="text" name="ds_telegram_chat_id" id="ds_telegram_chat_id"
                                   value="<?= esc_attr(get_option('ds_telegram_chat_id')); ?>" 
                                   class="regular-text" placeholder="-100123456789">
                            <p class="description">آی‌دی چت یا گروه تلگرام</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>

            <hr>
            <h2>📊 داشبورد گزارش و لاگ</h2>
            <textarea style="width:100%; height:400px; font-family:monospace; font-size:12px; background:#f8f8f8; padding:10px;" readonly><?= $logs ?></textarea>
            <br><br>
            <button class="button button-secondary" id="ds-clear-log">خالی کردن لاگ</button>
            <button class="button button-secondary" id="ds-delete-log">حذف کامل فایل لاگ</button>
            <p id="ds-log-status" style="margin-top:10px; font-weight:bold;"></p>

            <script>
            jQuery(function($){
                $('#ds-clear-log').click(function(){
                    if (!confirm('آیا مطمئن هستید که لاگ خالی شود؟')) return;
                    $.post(ajaxurl, {action: 'ds_clear_log'}, function(res){
                        $('#ds-log-status').text(res.data || 'لاگ خالی شد').css('color', 'green');
                        setTimeout(() => location.reload(), 1200);
                    });
                });
                $('#ds-delete-log').click(function(){
                    if (!confirm('آیا مطمئن هستید که فایل لاگ کاملاً حذف شود؟')) return;
                    $.post(ajaxurl, {action: 'ds_delete_log'}, function(res){
                        $('#ds-log-status').text(res.data || 'فایل لاگ حذف شد').css('color', 'green');
                        setTimeout(() => location.reload(), 1200);
                    });
                });
            });
            </script>

            <hr>
            <h2>🔧 اصلاح رسانه‌های قبلی</h2>
            <p>این عملیات رسانه‌های قبلی را به صورت مرحله‌ای اصلاح می‌کند و فشار به سرور وارد نمی‌کند.</p>
            <button type="button" class="button button-primary" id="ds-fix-media">
                شروع اصلاح رسانه‌ها
            </button>
            <p id="ds-fix-status" style="margin-top:10px;"></p>

            <script>
            jQuery(function($){
                $('#ds-fix-media').on('click', function(){
                    var btn = $(this);
                    btn.prop('disabled', true);
                    $('#ds-fix-status').text('در حال پردازش...');
                    
                    function runBatch(){
                        $.post(ajaxurl, {
                            action: 'ds_fix_media_batch',
                            _ajax_nonce: '<?php echo wp_create_nonce('ds_fix_media_nonce'); ?>'
                        }, function(res){
                            if (res.success) {
                                $('#ds-fix-status').text(res.data.message);
                                if (res.data.done) {
                                    btn.prop('disabled', false);
                                } else {
                                    runBatch();
                                }
                            } else {
                                $('#ds-fix-status').text('خطا: ' + res.data);
                                btn.prop('disabled', false);
                            }
                        });
                    }
                    runBatch();
                });
            });
            </script>
        </div>
        <?php
    }
}