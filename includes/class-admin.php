<?php
namespace DownloadServer;

class Admin
{
    private Settings $settings;
    private Logger $logger;

    public function __construct(Settings $settings, Logger $logger) {
        $this->settings = $settings;
        $this->logger   = $logger;
    }

    public function register(): void {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_init', [$this->settings, 'register']);
        add_filter('manage_upload_columns', [$this, 'addColumn']);
        add_action('manage_media_custom_column', [$this, 'renderColumn'], 10, 2);
        add_action('admin_footer-upload.php', [$this, 'renderAdminJs']);
    }

    public function addMenu(): void {
        add_options_page(
            'تنظیمات سرور دانلود',
            'سرور دانلود',
            'manage_options',
            'download-media-settings',
            [$this, 'renderSettingsPage']
        );
    }

    public function addColumn(array $columns): array {
        $columns['download_uploaded'] = 'آپلود در سرور دانلود';
        return $columns;
    }

    public function renderColumn(string $column, int $postId): void {
        if ($column !== 'download_uploaded') return;

        $relative = get_post_meta($postId, '_wp_attached_file', true);
        $uploaded = get_post_meta($postId, '_download_uploaded', true);

        if (!$uploaded || !$relative) {
            echo '<button class="button download-upload-btn" data-id="' . esc_attr($postId) . '">آپلود به سرور دانلود</button>';
            echo '<span class="download-upload-msg" id="download-msg-' . esc_attr($postId) . '"></span>';
            return;
        }

        $url = $this->settings->getDownloadUrl() . '/' . str_replace('\\', '/', $relative);
        
        // بررسی وجود با استفاده از Storage
        $storage = Plugin::storage();
        if ($storage->exists($relative)) {
            echo '<a href="' . esc_url($url) . '" target="_blank" style="color:green;">✓ آپلود شده</a>';
        } else {
            echo '<button class="button download-upload-btn" data-id="' . esc_attr($postId) . '">آپلود به سرور دانلود</button>';
            echo '<span class="download-upload-msg" id="download-msg-' . esc_attr($postId) . '"></span>';
        }
    }

    public function renderAdminJs(): void {
        ?>
        <script>
        jQuery(document).ready(function($){
            $('.download-upload-btn').click(function(){
                var btn = $(this);
                var postId = btn.data('id');
                var msg = $('#download-msg-' + postId);
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
                    msg.text('✗ خطای ارتباط').css('color', 'red');
                    btn.prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }

    public function renderSettingsPage(): void {
        $logs = htmlspecialchars($this->logger->getContents() ?: 'هیچ لاگی ثبت نشده است.');
        ?>
        <div class="wrap">
            <h1>تنظیمات سرور دانلود</h1>
            <form method="post" action="options.php">
                <?php settings_fields('download_media_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th><label>فعال‌سازی</label></th>
                        <td><input type="checkbox" name="<?= Settings::OPTION_ENABLED ?>" value="1" <?= checked(get_option(Settings::OPTION_ENABLED, true), true, false); ?>></td>
                    </tr>
                    <tr>
                        <th><label>آدرس فایل‌ها</label></th>
                        <td><input type="text" name="<?= Settings::OPTION_URL ?>" value="<?= esc_attr(get_option(Settings::OPTION_URL)); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label>آدرس upload.php</label></th>
                        <td><input type="text" name="<?= Settings::OPTION_UPLOAD_URL ?>" value="<?= esc_attr(get_option(Settings::OPTION_UPLOAD_URL)); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label>کلید مخفی</label></th>
                        <td><input type="text" name="<?= Settings::OPTION_SECRET_KEY ?>" value="<?= esc_attr(get_option(Settings::OPTION_SECRET_KEY)); ?>" class="regular-text"></td>
                    </tr>
                </table>
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>

            <hr>
            <h2>داشبورد لاگ</h2>
            <textarea style="width:100%;height:400px;font-family:monospace;" readonly><?= $logs ?></textarea>
            <br><br>
            <button class="button" id="ds-clear-log">خالی کردن لاگ</button>
            <button class="button" id="ds-delete-log">حذف فایل لاگ</button>
            <p id="ds-log-status"></p>

            <script>
            jQuery(function($){
                $('#ds-clear-log').click(() => {
                    if (!confirm('لاگ خالی شود؟')) return;
                    $.post(ajaxurl, {action: 'ds_clear_log'}, () => location.reload());
                });
                $('#ds-delete-log').click(() => {
                    if (!confirm('فایل لاگ حذف شود؟')) return;
                    $.post(ajaxurl, {action: 'ds_delete_log'}, () => location.reload());
                });
            });
            </script>
        </div>
        <?php
    }
}