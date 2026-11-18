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

use ArrayObject;
use Dotclear\App;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Process\TraitProcess;
use Throwable;

/**
 * Frontend: rewrite <img> tags of HTML pages to use the WebP copies.
 *
 * Uses the urlHandlerServeDocument behaviour (registered after the theme's
 * own handlers), which receives the whole page before Dotclear computes its ETag, so post content, excerpts and theme
 * thumbnails are all covered and the ETag matches what is sent. Feeds and
 * other non-HTML documents are left alone.
 */
class Frontend
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::FRONTEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        if (My::config()['mode'] === My::MODE_OFF) {
            return true;
        }

        // Register the page rewrite once the theme's _public.php is loaded (publicPrependV2
        // fires right after it), so it runs after any urlHandlerServeDocument filter of the
        // theme (for example one that swaps the production host for a dev host).
        App::behavior()->addBehavior('publicPrependV2', function (): string {
            App::behavior()->addBehavior('urlHandlerServeDocument', self::serveDocument(...));

            return '';
        });

        return true;
    }

    /**
     * @param   ArrayObject<string, mixed>  $result
     */
    public static function serveDocument(ArrayObject $result): string
    {
        try {
            $type = isset($result['content_type']) && is_string($result['content_type']) ? $result['content_type'] : '';
            if (!isset($result['content']) || !is_string($result['content']) || !str_starts_with(strtolower($type), 'text/html')) {
                return '';
            }

            $result['content'] = self::rewriter()->rewrite($result['content']);
        } catch (Throwable) {
            // Never break a page for this
        }

        return '';
    }

    public static function rewriter(): Rewriter
    {
        // Same logic as Dotclear's Media constructor
        $public_url = App::blog()->settings()->get('system')->getStr('public_url', false);
        if ((bool) preg_match('#^http(s)?://#', $public_url)) {
            $root_url = rawurldecode($public_url);
        } else {
            $root_url = rawurldecode(App::blog()->host() . Path::clean($public_url));
        }

        // The request's host too: a copied database (dev) can keep another blog URL
        $request_host = isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST']) ? [$_SERVER['HTTP_HOST']] : [];

        return new Rewriter(App::blog()->publicPath(), $root_url, App::blog()->host(), My::config()['mode'], $request_host);
    }
}
