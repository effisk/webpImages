# Changelog

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
