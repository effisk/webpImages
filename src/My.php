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

use Dotclear\Module\MyPlugin;

/**
 * Module helper.
 *
 * Default contexts: MANAGE/MENU need admin permission on the blog,
 * BACKEND needs usage/contentadmin, FRONTEND is always allowed.
 */
class My extends MyPlugin
{
    /** Default WebP quality (0-100). */
    public const DEFAULT_QUALITY = 80;

    /** Delivery modes. */
    public const MODE_PICTURE = 'picture';
    public const MODE_REPLACE = 'replace';
    public const MODE_OFF     = 'off';

    /**
     * Plugin settings with defaults applied.
     *
     * @return array{active: bool, quality: int, mode: string, thumbnails: bool}
     */
    public static function config(): array
    {
        $s = self::settings();

        $quality = $s->getInt('quality', false);
        if ($quality < 1 || $quality > 100) {
            $quality = self::DEFAULT_QUALITY;
        }

        $mode = $s->getStr('mode', false);
        if (!in_array($mode, [self::MODE_PICTURE, self::MODE_REPLACE, self::MODE_OFF], true)) {
            $mode = self::MODE_PICTURE;
        }

        return [
            // Settings that were never saved read as "not set": default to on.
            'active'     => $s->settingExists('active') ? (bool) $s->getBool('active', false) : true,
            'quality'    => $quality,
            'mode'       => $mode,
            'thumbnails' => $s->settingExists('thumbnails') ? (bool) $s->getBool('thumbnails', false) : true,
        ];
    }
}
