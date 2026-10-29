<?php
namespace DownloadServer;

class Settings
{
    public const OPTION_URL        = 'download_url';
    public const OPTION_UPLOAD_URL = 'download_upload_url';
    public const OPTION_SECRET_KEY = 'download_secret_key';
    public const OPTION_ENABLED    = 'download_enabled';

    public function getDownloadUrl(): string {
        return rtrim(get_option(self::OPTION_URL, ''), '/');
    }

    public function getUploadUrl(): string {
        return rtrim(get_option(self::OPTION_UPLOAD_URL, ''), '/');
    }

    public function getSecretKey(): string {
        return get_option(self::OPTION_SECRET_KEY, '');
    }

    public function isEnabled(): bool {
        return (bool) get_option(self::OPTION_ENABLED, true);
    }

    public function register(): void {
        register_setting('download_media_settings', self::OPTION_URL);
        register_setting('download_media_settings', self::OPTION_UPLOAD_URL);
        register_setting('download_media_settings', self::OPTION_SECRET_KEY);
        register_setting('download_media_settings', self::OPTION_ENABLED);
    }
}