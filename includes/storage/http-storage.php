<?php
namespace DownloadServer\Storage;

use DownloadServer\Settings;
use DownloadServer\Logger;

class HttpStorage implements StorageInterface
{
    private Settings $settings;
    private Logger $logger;

    public function __construct(Settings $settings, Logger $logger) {
        $this->settings = $settings;
        $this->logger   = $logger;
    }

    public function upload(string $localPath, string $remotePath): bool {
        if (!file_exists($localPath)) {
            $this->logger->error("فایل محلی وجود ندارد", ['path' => $localPath]);
            return false;
        }

        $ch = curl_init($this->settings->getUploadUrl());
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'key'  => $this->settings->getSecretKey(),
                'file' => new \CURLFile($localPath),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logger->error("خطای CURL در آپلود", ['error' => $error]);
            return false;
        }

        if (trim($response) === 'OK') {
            $this->logger->info("آپلود موفق", ['file' => $remotePath]);
            return true;
        }

        $this->logger->error("آپلود ناموفق", [
            'file'     => $remotePath,
            'response' => $response,
        ]);
        return false;
    }

    public function delete(string $remotePath): bool {
        $ch = curl_init($this->settings->getUploadUrl());
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'key'    => $this->settings->getSecretKey(),
                'action' => 'delete',
                'file'   => $remotePath,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (trim($response) === 'DELETED') {
            $this->logger->info("حذف موفق", ['file' => $remotePath]);
            return true;
        }

        $this->logger->error("حذف ناموفق", [
            'file'     => $remotePath,
            'response' => $response,
        ]);
        return false;
    }

    public function exists(string $remotePath): bool {
        $url = $this->getUrl($remotePath);
        $cacheKey = 'ds_check_' . md5($url);
        $cached = get_transient($cacheKey);
        if ($cached !== false) return (bool) $cached;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $exists = ($code === 200);
        set_transient($cacheKey, $exists ? 1 : 0, 3 * HOUR_IN_SECONDS);
        return $exists;
    }

    public function getUrl(string $remotePath): string {
        return $this->settings->getDownloadUrl() . '/' . str_replace('\\', '/', $remotePath);
    }
}