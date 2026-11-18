<?php

/**
 * @brief webpImages, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Plugin\webpImages;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * JPEG/PNG -> WebP conversion and file naming.
 *
 * Naming: the WebP copy sits next to its source as a hidden file, with the
 * source extension kept, so photo.jpg and photo.png can't collide and the
 * media manager doesn't list it (it hides dot-files, like thumbnails):
 *
 *   shows/photo.jpg      -> shows/.photo.jpg.webp
 *   shows/.photo_m.jpg   -> shows/.photo_m.jpg.webp   (Dotclear thumbnail)
 *
 * This class only uses plain PHP (GD or Imagick), so it can be tested
 * outside Dotclear.
 */
class Converter
{
    public const STATUS_CREATED  = 'created';
    public const STATUS_UPTODATE = 'uptodate';
    public const STATUS_BIGGER   = 'bigger';    // WebP not smaller than source: not kept
    public const STATUS_TOOLARGE = 'toolarge';  // too many pixels for the memory limit
    public const STATUS_ERROR    = 'error';
    public const STATUS_SKIPPED  = 'skipped';   // not a JPEG/PNG

    /** Hard cap on pixels (about 50 megapixels). */
    public const MAX_PIXELS = 50_000_000;

    /** Source extensions handled. */
    public const EXTENSIONS = ['jpg', 'jpeg', 'jpe', 'png'];

    /** Last error message (for reports). */
    public static string $lastError = '';

    /**
     * Which encoder is available: 'gd', 'imagick' or '' (none).
     */
    public static function engine(): string
    {
        if (function_exists('imagewebp') && function_exists('imagecreatefromjpeg') && function_exists('imagecreatefrompng')) {
            return 'gd';
        }

        if (class_exists('Imagick')) {
            try {
                if (\Imagick::queryFormats('WEBP') !== []) {
                    return 'imagick';
                }
            } catch (Throwable) {
            }
        }

        return '';
    }

    public static function isCandidate(string $file): bool
    {
        return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
    }

    /**
     * Path of the WebP copy of a source file.
     */
    public static function webpPathFor(string $source): string
    {
        $dir  = dirname($source);
        $base = basename($source);

        return $dir . '/' . (str_starts_with($base, '.') ? '' : '.') . $base . '.webp';
    }

    /**
     * File name (no directory) of the WebP copy of a source file name.
     */
    public static function webpNameFor(string $basename): string
    {
        return (str_starts_with($basename, '.') ? '' : '.') . $basename . '.webp';
    }

    /**
     * Create (or refresh) the WebP copy of one file.
     */
    public static function convert(string $source, int $quality = My::DEFAULT_QUALITY, bool $force = false): string
    {
        self::$lastError = '';

        if (!self::isCandidate($source)) {
            return self::STATUS_SKIPPED;
        }

        if (!is_file($source) || !is_readable($source)) {
            self::$lastError = 'Source not readable';

            return self::STATUS_ERROR;
        }

        $dest = self::webpPathFor($source);

        if (!$force && is_file($dest) && (int) @filemtime($dest) >= (int) @filemtime($source)) {
            return self::STATUS_UPTODATE;
        }

        if (!is_writable(dirname($source))) {
            self::$lastError = 'Folder not writable: ' . dirname($source);

            return self::STATUS_ERROR;
        }

        $info = @getimagesize($source);
        if ($info === false) {
            self::$lastError = 'Not a readable image';

            return self::STATUS_ERROR;
        }

        [$width, $height] = $info;
        if (!self::fitsInMemory((int) $width, (int) $height)) {
            self::$lastError = sprintf('Too large (%d x %d)', $width, $height);

            return self::STATUS_TOOLARGE;
        }

        $quality = max(1, min(100, $quality));

        try {
            $data = match (self::engine()) {
                'gd'      => self::encodeWithGd($source, (int) $info[2], $quality),
                'imagick' => self::encodeWithImagick($source, $quality),
                default   => null,
            };
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            $data            = null;
        }

        if ($data === null || $data === '') {
            if (self::$lastError === '') {
                self::$lastError = self::engine() === '' ? 'No WebP encoder (GD or Imagick) on this server' : 'Encoding failed';
            }

            return self::STATUS_ERROR;
        }

        // Not worth it: remove any stale copy so the browser gets the original
        if (strlen($data) >= (int) @filesize($source)) {
            if (is_file($dest)) {
                @unlink($dest);
            }

            return self::STATUS_BIGGER;
        }

        // Atomic write: a visitor never gets a half-written file
        $tmp = $dest . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $data) === false) {
            self::$lastError = 'Unable to write ' . $tmp;

            return self::STATUS_ERROR;
        }

        @chmod($tmp, 0666 & ~umask());
        if (!@rename($tmp, $dest)) {
            @unlink($tmp);
            self::$lastError = 'Unable to write ' . $dest;

            return self::STATUS_ERROR;
        }

        return self::STATUS_CREATED;
    }

    /**
     * Remove the WebP copy of a source file (if any).
     */
    public static function remove(string $source): void
    {
        $dest = self::webpPathFor($source);
        if (is_file($dest)) {
            @unlink($dest);
        }
    }

    /**
     * Move the WebP copy along with its source (media rename/move).
     */
    public static function move(string $oldSource, string $newSource): void
    {
        $old = self::webpPathFor($oldSource);
        $new = self::webpPathFor($newSource);
        if ($old !== $new && is_file($old)) {
            if (is_file($new)) {
                @unlink($new);
            }
            @rename($old, $new);
        }
    }

    /**
     * Sorted list of the JPEG/PNG files under a folder, as paths relative to it.
     *
     * @return list<string>
     */
    public static function scan(string $root, bool $includeThumbnails = true): array
    {
        $root  = rtrim($root, '/');
        $files = [];

        if (!is_dir($root)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO),
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        $len = strlen($root) + 1;
        foreach ($iterator as $info) {
            /** @var \SplFileInfo $info */
            if (!$info->isFile() || $info->isLink()) {
                continue;
            }

            $name = $info->getFilename();
            if (!self::isCandidate($name)) {
                continue;
            }

            if (!$includeThumbnails && str_starts_with($name, '.')) {
                continue;
            }

            $relative = substr($info->getPathname(), $len);

            // Skip hidden folders (e.g. .git, .well-known) but not hidden files
            if (preg_match('#(^|/)\.[^/]*/#', $relative)) {
                continue;
            }

            $files[] = $relative;
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * Rough check that GD can hold the image (source + rotated copy) in memory.
     */
    private static function fitsInMemory(int $width, int $height): bool
    {
        $pixels = $width * $height;
        if ($pixels <= 0 || $pixels > self::MAX_PIXELS) {
            return false;
        }

        $limit = self::bytes((string) ini_get('memory_limit'));
        if ($limit <= 0) {
            return true;    // no limit
        }

        // GD truecolor: ~5 bytes per pixel, x2 for a rotation copy, plus margin
        $needed = (int) ($pixels * 5 * 2 * 1.2);
        $free   = $limit - memory_get_usage(true);
        if ($needed < $free) {
            return true;
        }

        // Try to raise the limit for this request
        $wanted = memory_get_usage(true) + $needed + 16 * 1024 * 1024;
        if (@ini_set('memory_limit', (string) $wanted) !== false) {
            return self::bytes((string) ini_get('memory_limit')) >= $wanted;
        }

        return false;
    }

    private static function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }

        $unit   = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g'     => $number * 1024 * 1024 * 1024,
            'm'     => $number * 1024 * 1024,
            'k'     => $number * 1024,
            default => $number,
        };
    }

    private static function encodeWithGd(string $source, int $type, int $quality): ?string
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG  => @imagecreatefrompng($source),
            default        => false,
        };

        if ($image === false) {
            self::$lastError = $type === IMAGETYPE_JPEG || $type === IMAGETYPE_PNG ? 'GD could not read the image' : 'Unsupported image type';

            return null;
        }

        if ($type === IMAGETYPE_PNG) {
            // WebP needs truecolor; keep transparency
            if (!imageistruecolor($image)) {
                imagepalettetotruecolor($image);
            }
            imagealphablending($image, false);
            imagesavealpha($image, true);
        } else {
            // Browsers apply the JPEG EXIF orientation; WebP has no EXIF here, so apply it now
            $image = self::applyExifOrientation($image, $source);
        }

        ob_start();
        $ok   = imagewebp($image, null, $quality);
        $data = (string) ob_get_clean();
        imagedestroy($image);

        return $ok ? $data : null;
    }

    private static function applyExifOrientation(\GdImage $image, string $source): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif        = @exif_read_data($source);
        $orientation = is_array($exif) && isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;

        $rotate = static function (\GdImage $img, int $angle): \GdImage {
            $rotated = imagerotate($img, $angle, 0);
            if ($rotated === false) {
                return $img;
            }
            imagedestroy($img);

            return $rotated;
        };

        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);

                break;
            case 3:
                $image = $rotate($image, 180);

                break;
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);

                break;
            case 5:
                $image = $rotate($image, -90);
                imageflip($image, IMG_FLIP_HORIZONTAL);

                break;
            case 6:
                $image = $rotate($image, -90);

                break;
            case 7:
                $image = $rotate($image, 90);
                imageflip($image, IMG_FLIP_HORIZONTAL);

                break;
            case 8:
                $image = $rotate($image, 90);

                break;
        }

        return $image;
    }

    private static function encodeWithImagick(string $source, int $quality): ?string
    {
        $image = new \Imagick($source);

        if (method_exists($image, 'autoOrient')) {
            $image->autoOrient();
        } else {
            switch ($image->getImageOrientation()) {
                case \Imagick::ORIENTATION_TOPRIGHT:
                    $image->flopImage();

                    break;
                case \Imagick::ORIENTATION_BOTTOMRIGHT:
                    $image->rotateImage('#000', 180);

                    break;
                case \Imagick::ORIENTATION_BOTTOMLEFT:
                    $image->flipImage();

                    break;
                case \Imagick::ORIENTATION_LEFTTOP:
                    $image->rotateImage('#000', 90);
                    $image->flopImage();

                    break;
                case \Imagick::ORIENTATION_RIGHTTOP:
                    $image->rotateImage('#000', 90);

                    break;
                case \Imagick::ORIENTATION_RIGHTBOTTOM:
                    $image->rotateImage('#000', -90);
                    $image->flopImage();

                    break;
                case \Imagick::ORIENTATION_LEFTBOTTOM:
                    $image->rotateImage('#000', -90);

                    break;
            }
        }

        $image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
        $image->stripImage();
        $image->setImageFormat('webp');
        $image->setImageCompressionQuality($quality);
        $image->setOption('webp:method', '4');

        $data = $image->getImageBlob();
        $image->clear();

        return $data;
    }
}
