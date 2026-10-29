<?php
namespace DownloadServer;

class Ajax
{
    private MediaHandler $media;
    private Logger $logger;

    public function __construct(MediaHandler $media, Logger $logger) {
        $this->media  = $media;
        $this->logger = $logger;
    }

    public function register(): void {
        add_action('wp_ajax_ds_upload_media', [$this, 'handleUpload']);
        add_action('wp_ajax_ds_clear_log', [$this, 'handleClearLog']);
        add_action('wp_ajax_ds_delete_log', [$this, 'handleDeleteLog']);
    }

    public function handleUpload(): void {
        check_ajax_referer('ds_upload_nonce');
        $postId = intval($_POST['post_id'] ?? 0);

        if (!$postId || !current_user_can('upload_files')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }

        if ($this->media->uploadManual($postId)) {
            wp_send_json_success();
        }
        wp_send_json_error('آپلود ناموفق بود');
    }

    public function handleClearLog(): void {
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز');
        $this->logger->clear();
        wp_send_json_success('لاگ خالی شد');
    }

    public function handleDeleteLog(): void {
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز');
        $this->logger->delete();
        wp_send_json_success('فایل لاگ حذف شد');
    }
}