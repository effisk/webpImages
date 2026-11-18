# Changelog

## 1.0.4 (2026-11-18)

- Existing `<picture>` elements (for example a theme's art-directed lead image with a mobile `<source>`) now get WebP too. In *picture* mode, a WebP `<source>` is added before each JPEG/PNG `<source>`, with the same `media` and `sizes`, and another one before the `<img>`. In *replace* mode their `srcset`s point to the WebP files.
- A `<picture>` that already has a WebP `<source>` is left as it is. `class="no-webp"` or `data-no-webp` on the `<picture>` or its `<img>` also leaves it alone.

## 1.0.3 (2026-11-18)

- First public release.
- French translation of the admin page.
- Batch progress labels are now translatable.
- Updates are offered by Dotclear from this repository (`dcstore.xml`).

## 1.0.2

- The page rewrite runs after the theme's own `urlHandlerServeDocument` filters, so a theme that changes image URLs (for example swapping a production host for a dev host) no longer hides them from the plugin.

## 1.0.1

- Absolute image URLs on the host the page was requested from are recognised too.

## 1.0

- WebP copies of JPEG/PNG images and thumbnails on upload, rename, move and delete.
- Batch conversion of existing images.
- `picture`, `replace` and `off` serving modes.
