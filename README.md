# WebP images

[![Dotclear](https://img.shields.io/badge/dotclear-2.36%2B-137bbb.svg)](https://dotclear.org/download)
[![License](https://img.shields.io/badge/license-AGPL--3.0-blue.svg)](LICENSE)

A [Dotclear](https://dotclear.org) plugin that creates WebP copies of your blog's JPEG and PNG images, and makes your pages use them.

On one blog with about 17,000 images and thumbnails, the WebP copies were 47% lighter than the JPEG/PNG originals overall.

*Une description en français suit la version anglaise.*

## Features

- **Converts on upload.** Every JPEG/PNG uploaded to the media manager gets a WebP copy, and so do its thumbnails (`_t`, `_s`, `_m`, `_o`, `_sq`). Renaming or moving a file in the media manager moves its copies, and deleting the file deletes them.
- **Converts existing images.** One button goes through the whole public folder in short steps. It can be stopped and resumed, and it skips images that are already up to date.
- **Serves them on the blog.** The plugin changes the page HTML so browsers load the WebP files. Social and SEO tags (og:image, twitter:image, JSON-LD) and feeds keep the original JPEG/PNG.
- **Safe conversion.** It applies the JPEG EXIF orientation and keeps PNG transparency. It doesn't keep a WebP copy that would be larger than the original, and it skips images too big for PHP's memory limit. A WebP error never blocks an upload.
- **Translated** into English and French.

## Requirements

- Dotclear 2.36 or later (tested on 2.40).
- PHP 8.1 or later, with **GD built with WebP support**, or the **Imagick** extension with WEBP support. The plugin page shows which encoder it found.
- Admin permission on the blog.
- Your web server must send `.webp` files as `image/webp`. On Apache, if needed:

  ```apache
  AddType image/webp .webp
  ```

  To check: `curl -sI https://example.com/public/some-folder/.photo.jpg.webp` should show `Content-Type: image/webp`.

## Installation

- From the [releases](https://github.com/effisk/webpImages/releases): download `plugin-webpImages-x.y.z.zip`, then in Dotclear go to *System settings → Plugins management → Install or upgrade manually* and upload it.
- Or unzip it into your `plugins/` folder, so that you have `plugins/webpImages/_define.php`.

Once installed, Dotclear's plugin manager offers new versions automatically (from this repository's `dcstore.xml`).

## Usage

1. Go to *Blog → WebP images*.
2. Check that the *Status* box shows an encoder.
3. Under *Convert existing images*, click **Start** and leave the page open until it says *Finished*.

### Settings

| Setting | Default | |
|---|---|---|
| Create a WebP copy of each upload | on | |
| Also convert thumbnails | on | Listings and widgets mostly use thumbnails. |
| WebP quality | 80 | Changing it only applies to new uploads. To redo existing copies, tick *Re-create every WebP copy* and click **Start**. |
| Serving | picture | See below. |

### Serving modes

| Mode | Output | |
|---|---|---|
| **picture** (default) | `<picture><source type="image/webp" srcset="….webp"><img src="….jpg" …></picture>` | Browsers without WebP support get the original. It adds a wrapper element, so check CSS rules such as `p > img`. |
| **replace** | `<img src="….webp" …>` | No new markup. All current browsers read WebP. |
| **off** | No change | For servers that pick WebP themselves (see below). |

Only `<img>` tags pointing into the blog's public folder (root-relative URLs, or absolute URLs on the blog's host) whose WebP copy exists are changed. These are never touched:

- `<meta>` tags and JSON-LD;
- feeds and other non-HTML responses;
- `<script>`, `<style>`, `<textarea>` and `<noscript>`, HTML comments and existing `<picture>` elements;
- URLs with a query string.

To leave an image alone, add `class="no-webp"` or a `data-no-webp` attribute to it.

The rewrite runs after the theme's own `urlHandlerServeDocument` filters. 404 pages are not rewritten, because Dotclear prints them without that behaviour.

### File names

Each copy sits next to its source as a hidden file, with the source extension kept, so `photo.jpg` and `photo.png` can't overwrite each other:

```
public/shows/photo.jpg      → public/shows/.photo.jpg.webp
public/shows/.photo_m.jpg   → public/shows/.photo_m.jpg.webp
```

The media manager hides dot-files, as it does for thumbnails.

### Letting the web server choose (mode "off")

To leave the HTML untouched, set the mode to **off** and let Apache serve the WebP bytes to browsers that ask for them. The rules below are an untested starting point: try them on a staging site first.

```apache
# public/.htaccess
<IfModule mod_rewrite.c>
  RewriteEngine On

  # Thumbnails: .photo_m.jpg -> .photo_m.jpg.webp
  RewriteCond %{HTTP_ACCEPT} image/webp
  RewriteCond %{REQUEST_FILENAME}.webp -f
  RewriteRule ^(.*/)?(\.[^/]+\.(?:jpe?g|png))$ $1$2.webp [NC,T=image/webp,L]

  # Originals: photo.jpg -> .photo.jpg.webp
  RewriteCond %{HTTP_ACCEPT} image/webp
  RewriteCond %{REQUEST_FILENAME} ^(.*/)([^/]+)$
  RewriteCond %1.%2.webp -f
  RewriteRule ^(.*/)?([^./][^/]*\.(?:jpe?g|png))$ $1.$2.webp [NC,T=image/webp,L]
</IfModule>
<IfModule mod_headers.c>
  <FilesMatch "\.(jpe?g|png)$">
    Header append Vary Accept
  </FilesMatch>
</IfModule>
AddType image/webp .webp
```

Any CDN or proxy in front of the site must honour `Vary: Accept`.

## Uninstalling

Delete the plugin in the plugin manager. The WebP files stay in your public folder. To remove them:

```sh
find public -type f -name '.*.webp' -delete
```

## Translations

The admin page uses Dotclear's usual locale files: `locales/<lang>/main.po`, with `main.lang.php` generated from it. To add a language, copy `locales/fr/main.po` to `locales/<lang>/main.po`, translate the `msgstr` lines, and send a pull request.

---

## En français

**WebP images** crée une copie WebP de chaque image JPEG/PNG du blog, miniatures comprises, et fait charger ces copies par les navigateurs. Les balises og:image, twitter:image, le JSON-LD et les flux gardent les originaux.

- **Installation :** téléchargez `plugin-webpImages-x.y.z.zip` depuis les [releases](https://github.com/effisk/webpImages/releases), puis *Réglages système → Gestion des plugins → Installer ou mettre à jour manuellement*. Les mises à jour suivantes sont ensuite proposées par Dotclear.
- **Prérequis :** Dotclear 2.36 ou plus, PHP 8.1 ou plus avec GD (support WebP) ou Imagick. Le serveur doit envoyer les `.webp` en `image/webp` : sur Apache, ajoutez `AddType image/webp .webp` si besoin.
- **Utilisation :** *Blog → Images WebP*, puis **Lancer** sous *Convertir les images existantes*. Les nouvelles images sont converties à l'envoi.
- **Modes de diffusion :**
  - *picture* (par défaut) : `<picture>` avec une source WebP ;
  - *replace* : `src` pointe directement vers le WebP ;
  - *off* : aucune modification.
- **Exclure une image :** ajoutez-lui `class="no-webp"`.

## License

[AGPL-3.0](LICENSE), like Dotclear.
