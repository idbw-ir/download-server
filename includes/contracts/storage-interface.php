<?php
namespace DownloadServer\Storage;

/**
 * اینترفیس Storage - برای افزودن S3، FTP و...
 */
interface StorageInterface
{
    /** آپلود فایل */
    public function upload(string $localPath, string $remotePath): bool;

    /** حذف فایل از سرور */
    public function delete(string $remotePath): bool;

    /** بررسی وجود فایل */
    public function exists(string $remotePath): bool;

    /** دریافت URL عمومی فایل */
    public function getUrl(string $remotePath): string;
}