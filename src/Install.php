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
use Dotclear\Helper\Process\TraitProcess;

/**
 * Install: default settings (global, so every blog of the install gets them;
 * each blog can then override them on the plugin page).
 */
class Install
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::INSTALL));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        $ws = App::blogWorkspace();
        My::settings()->put('active', true, $ws::NS_BOOL, 'Create WebP copies of uploaded images', false, true);
        My::settings()->put('thumbnails', true, $ws::NS_BOOL, 'Also convert media thumbnails', false, true);
        My::settings()->put('quality', My::DEFAULT_QUALITY, $ws::NS_INT, 'WebP quality (1-100)', false, true);
        My::settings()->put('mode', My::MODE_PICTURE, $ws::NS_STRING, 'Delivery: picture, replace or off', false, true);

        return true;
    }
}
