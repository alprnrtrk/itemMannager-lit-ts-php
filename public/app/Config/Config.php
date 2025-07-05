<?php
// public/app/Config/Config.php

namespace App\Config;

class Config
{
    // IMPORTANT: Change this password! Use a strong, unique password in production.
    // This should ideally be loaded from environment variables (.env file) for production security.
    const ADMIN_PASSWORD_SECRET = '1234';

    // Relative paths from the public directory (where index.php resides)
    const UPLOAD_DIR_RELATIVE = '/uploads/'; // Folder for uploaded images
    const DB_FILE_RELATIVE = '/data/items.sqlite'; // SQLite database file location

    /**
     * Get the absolute path to the uploads directory.
     * @param string $doc_root The document root (typically __DIR__ from public/index.php)
     * @return string Absolute path to the uploads directory.
     */
    public static function getUploadDirPath(string $doc_root): string
    {
        return $doc_root . self::UPLOAD_DIR_RELATIVE;
    }

    /**
     * Get the absolute path to the SQLite database file.
     * @param string $doc_root The document root (typically __DIR__ from public/index.php)
     * @return string Absolute path to the SQLite database file.
     */
    public static function getDbFilePath(string $doc_root): string
    {
        return $doc_root . self::DB_FILE_RELATIVE;
    }
}