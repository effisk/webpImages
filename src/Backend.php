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
use Dotclear\Core\Backend\Favorites;
use Dotclear\Core\Backend\Menus;
use Dotclear\Helper\Process\TraitProcess;

/**
 * Backend: admin menu entry and media manager file handlers.
 *
 * The media manager (App::media()) is built before plugins load in the
 * backend, so handlers added here run after Dotclear's own ones (metadata,
 * then thumbnails).
 */
class Backend
{
    use TraitProcess;

    public static function init(): bool
    {
        // Dead but useful code (for l10n)
        __('WebP images');
        __('Generate and serve WebP versions of the blog images');

        return self::status(My::checkContext(My::BACKEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        My::addBackendMenuItem(Menus::MENU_BLOG);

        App::behavior()->addBehavior('adminDashboardFavoritesV2', function (Favorites $favs): string {
            $favs->register(My::id(), [
                'title'          => My::name(),
                'url'            => My::manageUrl(),
                'menu-icon'      => My::icon(),
                'dashboard-icon' => My::icon(),
                'permissions'    => App::auth()->makePermissions([App::auth()::PERMISSION_ADMIN]),
            ]);

            return '';
        });

        if (App::blog()->isDefined() && !App::media()->isRootMissing()) {
            MediaHandlers::register();
        }

        return true;
    }
}
