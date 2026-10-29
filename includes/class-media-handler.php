<?php
namespace DownloadServer;

use DownloadServer\Storage\StorageInterface;
use DownloadServer\Notifier\NotifierManager;

class MediaHandler
{
    private StorageInterface $storage;
    private Settings $settings;
    private Logger $logger;
    private NotifierManager $notifiers;  // 🆕
    private array $filesToDelete = [];

    public function __construct(
        StorageInterface $storage,
        Settings $settings,
        Logger $logger,
        NotifierManager $notifiers   // 🆕
    ) {
        $this->storage   = $storage;
        $this->settings  = $settings;
        $this->logger    = $logger;
        $this->notifiers = $notifiers;
    }

    // ... سایر متدها ...

    public function onAddAttachment(int $postId): void
    {
        if (!$this->settings->isEnabled()) return;
        if (wp_attachment_is_image($postId)) return;

        $file = get_attached_file($postId);
        if (!$file || !file_exists($file)) return;

        $relative = $this->getRelativePath($file);

        if ($this->storage->upload($file, $relative)) {
            update_post_meta($postId, '_download_uploaded', 1);
            update_post_meta($postId, '_wp_attached_file', $relative);
            $this->markForDelete($file);

            // 🆕 اطلاع‌رسانی به همه کانال‌ها
            $this->notifiers->success('آپلود خودکار فایل موفق بود', [
                'file'    => $relative,
                'post_id' => $postId,
            ]);
        } else {
            // 🆕 اطلاع‌رسانی خطا
            $this->notifiers->error('آپلود خودکار فایل ناموفق بود', [
                'file'    => $relative,
                'post_id' => $postId,
            ]);
        }
    }

    public function onGenerateMetadata(array $metadata, int $postId): array
    {
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

        $successCount = 0;
        $failCount    = 0;

        foreach ($files as $file) {
            if (!file_exists($file)) continue;
            $relative = str_replace($basedir, '', $file);
            if ($this->storage->upload($file, $relative)) {
                $this->markForDelete($file);
                $successCount++;
            } else {
                $failCount++;
            }
        }

        update_post_meta($postId, '_download_uploaded', 1);
        update_post_meta($postId, '_wp_attached_file', $metadata['file']);

        // 🆕 اطلاع‌رسانی خلاصه
        if ($failCount === 0) {
            $this->notifiers->success('آپلود تصویر و سایزها موفق بود', [
                'post_id'    => $postId,
                'files_count' => count($files),
            ]);
        } else {
            $this->notifiers->error("{$failCount} فایل از آپلود ناموفق بود", [
                'post_id' => $postId,
                'success' => $successCount,
                'failed'  => $failCount,
            ]);
        }

        return $metadata;
    }

    public function onDeleteAttachment(int $postId): void
    {
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

        $deletedCount = 0;
        foreach ($files as $file) {
            if ($this->storage->delete($file)) $deletedCount++;
        }

        // 🆕 اطلاع‌رسانی حذف
        $this->notifiers->notify('🗑️ حذف', 'فایل از سرور دانلود حذف شد', [
            'post_id' => $postId,
            'files'   => count($files),
            'deleted' => $deletedCount,
        ]);
    }

    // ... سایر متدها ...

    private function getRelativePath(string $file): string
    {
        return str_replace(wp_get_upload_dir()['basedir'] . '/', '', $file);
    }
}