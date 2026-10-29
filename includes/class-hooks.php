<?php
namespace DownloadServer;

class Hooks
{
    private Settings $settings;

    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    public function register(): void {
        add_filter('wp_get_attachment_url', [$this, 'filterAttachmentUrl'], 10, 2);
        add_filter('wp_calculate_image_srcset', [$this, 'filterSrcset'], 10, 5);
    }

    public function filterAttachmentUrl(string $url, int $postId): string {
        if (!get_post_meta($postId, '_download_uploaded', true)) return $url;
        
        $relative = get_post_meta($postId, '_wp_attached_file', true);
        if (!$relative) return $url;

        return $this->settings->getDownloadUrl() . '/' . str_replace('\\', '/', $relative);
    }

    public function filterSrcset(array $sources, array $sizeArray, string $imageSrc, array $imageMeta, int $attachmentId): array {
        if (!get_post_meta($attachmentId, '_download_uploaded', true)) return $sources;

        $base = $this->settings->getDownloadUrl();
        foreach ($sources as &$source) {
            $parsed = wp_parse_url($source['url']);
            if (!empty($parsed['path'])) {
                $relative = ltrim(str_replace('/wp-content/uploads/', '', $parsed['path']), '/');
                $source['url'] = $base . '/' . $relative;
            }
        }
        return $sources;
    }
}