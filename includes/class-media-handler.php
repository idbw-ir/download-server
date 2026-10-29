<?php
namespace DownloadServer;

use DownloadServer\Storage\StorageInterface;

class MediaHandler
{
    private StorageInterface $storage;
    private Settings $settings;
    private Logger $logger;
    private array $filesToDelete = [];

    public function __construct(StorageInterface $storage, Settings $settings, Logger $logger) {
        $this->storage  = $storage;
        $this->settings = $settings;
        $this->logger   = $logger;
    }

    public function register(): void {
        // آپلود خودکار فایل‌های غیرتصویر
        add_action('add_attachment', [$this, 'onAddAttachment']);
        
        // آپلود سایزهای تصویر
        add_filter('wp_generate_attachment_metadata', [$this, 'onGenerateMetadata'], 10, 2);
        
        // حذف از سرور دانلود هنگام حذف رسانه
        add_action('delete_attachment', [$this, 'onDeleteAttachment']);
        
        // حذف فایل‌های محلی در shutdown
        add_action('shutdown', [$this, 'flushDeleteQueue']);
    }

    /** آپلود دستی از AJAX */
    public function uploadManual(int $postId): bool {
        $file = get_attached_file($postId);
        if (!$file || !file_exists($file)) return false;

        $relative = $this->getRelativePath($file);
        if ($this->storage->upload($file, $relative)) {
            update_post_meta($postId, '_download_uploaded', 1);
            update_post_meta($postId, '_wp_attached_file', $relative);
            $this->markForDelete($file);
            return true;
        }
        return false;
    }

    public function onAddAttachment(int $postId): void {
        if (!$this->settings->isEnabled()) return;
        if (wp_attachment_is_image($postId)) return;

        $file = get_attached_file($postId);
        if (!$file || !file_exists($file)) return;

        $relative = $this->getRelativePath($file);
        if ($this->storage->upload($file, $relative)) {
            update_post_meta($postId, '_download_uploaded', 1);
            update_post_meta($postId, '_wp_attached_file', $relative);
            $this->markForDelete($file);
        }
    }

    public function onGenerateMetadata(array $metadata, int $postId): array {
        if (!$this->settings->isEnabled()) return $metadata;
        if (!wp_attachment_is_image($postId)) return $metadata;

        $basedir = trailingslashit(wp_get_upload_dir()['basedir']);
        $files   = [$basedir . $metadata['file']];

        if (!empty($metadata['sizes'])) {
            $dir = trailingslashit(dirname($metadata['file']));
            foreach ($metadata['sizes'] as $size) {
                $files[] = $basedir . $dir . $size['file'];
            }
        }

        foreach ($files as $file) {
            if (!file_exists($file)) continue;
            $relative = str_replace($basedir, '', $file);
            if ($this->storage->upload($file, $relative)) {
                $this->markForDelete($file);
            }
        }

        update_post_meta($postId, '_download_uploaded', 1);
        update_post_meta($postId, '_wp_attached_file', $metadata['file']);
        return $metadata;
    }

    public function onDeleteAttachment(int $postId): void {
        if (!get_post_meta($postId, '_download_uploaded', true)) return;

        $relative = get_post_meta($postId, '_wp_attached_file', true);
        if (!$relative) return;

        $files = [$relative];
        $metadata = wp_get_attachment_metadata($postId);
        if (!empty($metadata['sizes'])) {
            $dir = trailingslashit(dirname($relative));
            foreach ($metadata['sizes'] as $size) {
                $files[] = $dir . $size['file'];
            }
        }

        foreach ($files as $file) {
            $this->storage->delete($file);
        }
    }

    public function markForDelete(string $file): void {
        if (file_exists($file)) $this->filesToDelete[] = $file;
    }

    public function flushDeleteQueue(): void {
        foreach ($this->filesToDelete as $file) {
            @unlink($file);
        }
    }

    private function getRelativePath(string $file): string {
        return str_replace(wp_get_upload_dir()['basedir'] . '/', '', $file);
    }
}