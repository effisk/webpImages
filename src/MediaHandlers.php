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

use Dotclear\App;
use Dotclear\Helper\File\File;
use Throwable;

/**
 * Media manager file handlers.
 *
 * Registered after Dotclear's own handlers, so on "create" the thumbnails
 * (.name_t.jpg, _s, _m, _o, _sq…) already exist and get a WebP copy too.
 * Nothing here may throw: a WebP problem must never break an upload.
 */
class MediaHandlers
{
    public const TYPES = ['image/jpeg', 'image/png'];

    public static function register(): void
    {
        $media = App::media();
        foreach (self::TYPES as $type) {
            $media->addFileHandler($type, 'create', self::onCreate(...));
            $media->addFileHandler($type, 'recreate', self::onCreate(...));
            $media->addFileHandler($type, 'update', self::onUpdate(...));
            $media->addFileHandler($type, 'remove', self::onRemove(...));
        }
    }

    /**
     * Upload, replace, or "rebuild thumbnails".
     *
     * @param   mixed   $cur    Cursor or null (unused)
     * @param   string  $f      File name, relative to the media manager's current folder
     */
    public static function onCreate(mixed $cur, string $f, int $id = 0, bool $force = true): void
    {
        try {
            $config = My::config();
            if (!$config['active']) {
                return;
            }

            $source = App::media()->getPwd() . '/' . $f;
            foreach (self::sourceAndThumbnails($source, $config['thumbnails']) as $file) {
                if (is_file($file)) {
                    Converter::convert($file, $config['quality'], $force);
                }
            }
        } catch (Throwable) {
        }
    }

    /**
     * Rename or move of a media file.
     */
    public static function onUpdate(mixed $file, mixed $newFile): void
    {
        try {
            if (!$file instanceof File || !$newFile instanceof File || $file->file === $newFile->file) {
                return;
            }

            $old = self::sourceAndThumbnails((string) $file->file, true);
            $new = self::sourceAndThumbnails((string) $newFile->file, true);
            foreach ($old as $i => $oldPath) {
                if (isset($new[$i])) {
                    Converter::move($oldPath, $new[$i]);
                }
            }
        } catch (Throwable) {
        }
    }

    /**
     * Deletion of a media file.
     *
     * @param   string  $f  File name, relative to the media manager's current folder
     */
    public static function onRemove(mixed $f): void
    {
        try {
            if (!is_string($f) || $f === '') {
                return;
            }

            $source = App::media()->getPwd() . '/' . $f;
            foreach (self::sourceAndThumbnails($source, true) as $file) {
                Converter::remove($file);
            }
        } catch (Throwable) {
        }
    }

    /**
     * The source file followed by its thumbnails, in a fixed order.
     *
     * @return list<string>
     */
    public static function sourceAndThumbnails(string $source, bool $withThumbnails): array
    {
        $files = [$source];
        if (!$withThumbnails) {
            return $files;
        }

        $media = App::media();
        $info  = pathinfo($source);
        $ext   = $info['extension'] ?? '';
        $base  = $info['filename'];
        $dir   = $info['dirname'] ?? '.';

        $pattern = $media->getThumbnailFilePattern($ext);
        $sizes   = array_keys($media->getThumbSizes());
        sort($sizes, SORT_STRING);

        foreach ($sizes as $suffix) {
            $files[] = sprintf($pattern, $dir, $base, (string) $suffix);
        }

        return $files;
    }
}
