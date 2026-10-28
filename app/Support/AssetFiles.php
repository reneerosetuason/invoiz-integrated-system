<?php

namespace App\Support;

/** Resolve untrusted relative paths only within explicitly selected disk roots. */
final class AssetFiles
{
    public const PRIVATE_DIRECTORIES = ['ids', 'seller-ids', 'seller-documents', 'seller-permits'];

    public static function clean(string $path): ?string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^/?storage/#', '', $path);
        if ($path === '' || str_starts_with($path, '/') || preg_match('/[\x00-\x1F\x7F:%?#]/', $path)) {
            return null;
        }
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..' || str_ends_with($part, '.') || str_ends_with($part, ' ')) {
                return null;
            }
        }
        return $path;
    }

    public static function isPrivate(string $path): bool
    {
        return in_array(strtolower(explode('/', $path)[0]), self::PRIVATE_DIRECTORIES, true);
    }

    public static function within(string $root, string $path): ?string
    {
        $clean = self::clean($path);
        $base = realpath($root);
        if ($clean === null || $base === false) {
            return null;
        }
        $file = realpath($base.DIRECTORY_SEPARATOR.$clean);
        if ($file === false || ! is_file($file)) {
            return null;
        }
        $prefix = rtrim(str_replace('\\', '/', $base), '/').'/';
        $resolved = str_replace('\\', '/', $file);
        if (PHP_OS_FAMILY === 'Windows') {
            $prefix = strtolower($prefix);
            $resolved = strtolower($resolved);
        }
        return str_starts_with($resolved, $prefix) ? $file : null;
    }

    public static function publicFile(string $path): ?string
    {
        $clean = self::clean($path);
        if ($clean === null || self::isPrivate($clean)) {
            return null;
        }
        // Only image assets belong on the unauthenticated fallback.
        if (! in_array(strtolower(pathinfo($clean, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'ico'], true)) {
            return null;
        }
        $file = self::within(\Illuminate\Support\Facades\Storage::disk('public')->path(''), $clean)
            ?? self::within(public_path('storage'), $clean);
        if (! $file) return null;
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file);
        return in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp', 'image/x-ms-bmp', 'image/x-icon', 'image/vnd.microsoft.icon'], true) ? $file : null;
    }

    public static function document(string $path): ?string
    {
        $clean = self::clean($path);
        if ($clean === null || ! self::isPrivate($clean)) {
            return null;
        }
        return self::within(\Illuminate\Support\Facades\Storage::disk('private')->path(''), $clean);
    }
}
