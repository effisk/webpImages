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

/**
 * Rewrites <img> tags of a page so browsers get the WebP copies.
 *
 * Only images under the blog's public folder that already have a WebP copy
 * are touched; everything else is left byte for byte. Meta tags (og:image,
 * twitter:image) and JSON-LD are never changed, so share images stay JPEG.
 *
 * Modes:
 *  - picture: <picture><source type="image/webp" srcset="…"><img …original…></picture>
 *  - replace: the <img> src/srcset point to the .webp files (no new markup)
 *
 * Opt out per image with class="no-webp" or a data-no-webp attribute.
 *
 * Plain PHP: build it with the blog's values, then call rewrite().
 */
class Rewriter
{
    /** @var array<string, string|null> URL -> WebP URL cache */
    private array $cache = [];

    /** @var list<string> Hosts that count as "this blog" (lower case) */
    private array $hosts;

    /** URL path of the public folder, no trailing slash (e.g. /blog/public) */
    private string $publicUrlPath;

    /**
     * @param   string  $publicPath     Filesystem path of the public folder
     * @param   string  $publicUrl      URL of the public folder (absolute or root-relative)
     * @param   string  $blogHost       Blog host URL (e.g. https://www.example.com)
     * @param   list<string>    $extraHosts     Other host names serving the same files (e.g. the request's host)
     */
    public function __construct(
        private string $publicPath,
        string $publicUrl,
        string $blogHost,
        private string $mode = My::MODE_PICTURE,
        array $extraHosts = []
    ) {
        $this->publicPath = rtrim($publicPath, '/');

        $hosts = [];
        foreach ([$publicUrl, $blogHost] as $url) {
            $host = parse_url($url, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $hosts[] = strtolower($host);
            }
        }
        foreach ($extraHosts as $host) {
            $host = strtolower((string) preg_replace('#:\d+$#', '', trim($host)));
            if ($host !== '') {
                $hosts[] = $host;
            }
        }
        $this->hosts = array_values(array_unique($hosts));

        $path                = parse_url($publicUrl, PHP_URL_PATH);
        $this->publicUrlPath = rtrim(is_string($path) ? $path : '', '/');
    }

    public function rewrite(string $html): string
    {
        if ($this->mode === My::MODE_OFF || $this->publicUrlPath === '' || stripos($html, '<img') === false) {
            return $html;
        }

        // Leave alone: comments, scripts, styles, textareas, noscript and existing <picture>
        $parts = preg_split(
            '#(<!--.*?-->|<script\b.*?</script\s*>|<style\b.*?</style\s*>|<textarea\b.*?</textarea\s*>|<noscript\b.*?</noscript\s*>|<picture\b.*?</picture\s*>)#is',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        if ($parts === false) {
            return $html;   // PCRE limit hit: serve the page unchanged
        }

        foreach ($parts as $i => $part) {
            if ($i % 2 === 1 || stripos($part, '<img') === false) {
                continue;
            }

            $result = preg_replace_callback('#<img\b[^>]*>#i', $this->rewriteTag(...), $part);
            if (is_string($result)) {
                $parts[$i] = $result;
            }
        }

        return implode('', $parts);
    }

    /**
     * @param   array<int, string>  $m
     */
    private function rewriteTag(array $m): string
    {
        $tag = $m[0];

        if (preg_match('#\sdata-no-webp\b#i', $tag)) {
            return $tag;
        }

        $class = self::attribute($tag, 'class');
        if ($class !== null && preg_match('#(^|\s)no-webp(\s|$)#', $class)) {
            return $tag;
        }

        $src    = self::attribute($tag, 'src');
        $srcset = self::attribute($tag, 'srcset');

        $webpSrc = $src !== null ? $this->webpUrl($src) : null;

        $webpSrcset = null;
        $allMapped  = true;
        if ($srcset !== null && trim($srcset) !== '') {
            $candidates = [];
            foreach (self::splitSrcset($srcset) as [$url, $descriptor]) {
                $webp = $this->webpUrl($url);
                if ($webp === null) {
                    $allMapped    = false;
                    $candidates[] = trim($url . ' ' . $descriptor);
                } else {
                    $candidates[] = trim($webp . ' ' . $descriptor);
                }
            }
            $webpSrcset = implode(', ', $candidates);
        }

        if ($this->mode === My::MODE_REPLACE) {
            if ($webpSrc !== null) {
                $tag = self::setAttribute($tag, 'src', $webpSrc);
            }
            if ($webpSrcset !== null) {
                $tag = self::setAttribute($tag, 'srcset', $webpSrcset);
            }

            return $tag;
        }

        // picture mode: the <source> must only list WebP files
        if ($srcset !== null && trim($srcset) !== '') {
            if (!$allMapped) {
                return $tag;
            }
            $sourceSrcset = (string) $webpSrcset;
        } elseif ($webpSrc !== null) {
            $sourceSrcset = $webpSrc;
        } else {
            return $tag;
        }

        $sizes  = self::attribute($tag, 'sizes');
        $source = '<source type="image/webp" srcset="' . self::escape($sourceSrcset) . '"'
            . ($sizes !== null ? ' sizes="' . self::escape($sizes) . '"' : '')
            . '>';

        return '<picture>' . $source . $tag . '</picture>';
    }

    /**
     * WebP URL for an image URL, or null if it isn't ours or has no WebP copy.
     */
    public function webpUrl(string $url): ?string
    {
        if (array_key_exists($url, $this->cache)) {
            return $this->cache[$url];
        }

        return $this->cache[$url] = $this->resolve($url);
    }

    private function resolve(string $url): ?string
    {
        $decoded = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded === '' || str_contains($decoded, '?') || str_contains($decoded, '#') || str_starts_with($decoded, 'data:')) {
            return null;
        }

        $parts = parse_url(str_starts_with($decoded, '//') ? 'https:' . $decoded : $decoded);
        if ($parts === false || !isset($parts['path'])) {
            return null;
        }

        if (isset($parts['host']) && !in_array(strtolower($parts['host']), $this->hosts, true)) {
            return null;
        }

        if (!isset($parts['host']) && !str_starts_with($parts['path'], '/')) {
            return null;    // relative URL: can't map it safely
        }

        $prefix = $this->publicUrlPath . '/';
        if (!str_starts_with($parts['path'], $prefix)) {
            return null;
        }

        $relative = rawurldecode(substr($parts['path'], strlen($prefix)));
        if ($relative === '' || str_contains($relative, "\0") || preg_match('#(^|/)\.\.(/|$)#', $relative)) {
            return null;
        }

        if (!Converter::isCandidate($relative)) {
            return null;
        }

        $webpFile = Converter::webpPathFor($this->publicPath . '/' . $relative);
        if (!is_file($webpFile)) {
            return null;
        }

        // Same URL, last segment swapped (keeps the original's host, scheme and encoding)
        $slash = strrpos($decoded, '/');
        $name  = basename($relative);

        return substr($decoded, 0, (int) $slash + 1) . rawurlencode(Converter::webpNameFor($name));
    }

    /**
     * Raw value of an attribute in a tag (entities not decoded), or null.
     */
    public static function attribute(string $tag, string $name): ?string
    {
        if (preg_match('#\s' . preg_quote($name, '#') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))#i', $tag, $m)) {
            foreach ([3, 2, 1] as $group) {
                if (isset($m[$group]) && $m[$group] !== '') {
                    return $m[$group];
                }
            }

            return '';
        }

        return null;
    }

    private static function setAttribute(string $tag, string $name, string $value): string
    {
        $replacement = ' ' . $name . '="' . self::escape($value) . '"';
        $result      = preg_replace(
            '#\s' . preg_quote($name, '#') . '\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'>]+)#i',
            $replacement,
            $tag,
            1
        );

        return is_string($result) ? $result : $tag;
    }

    /**
     * @return list<array{0: string, 1: string}>  [url, descriptor]
     */
    private static function splitSrcset(string $srcset): array
    {
        $out = [];
        foreach (preg_split('#,\s+#', trim($srcset)) ?: [] as $candidate) {
            $candidate = trim($candidate, " \t\n\r,");
            if ($candidate === '') {
                continue;
            }
            $bits  = preg_split('#\s+#', $candidate, 2) ?: [$candidate];
            $out[] = [$bits[0], $bits[1] ?? ''];
        }

        return $out;
    }

    /**
     * Escape for an attribute value, without double-encoding existing entities.
     */
    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
    }
}
