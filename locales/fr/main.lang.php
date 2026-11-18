<?php
declare(strict_types=1);

/**
 * @package Dotclear
 *
 * @copyright effisk
 * @copyright AGPL-3.0
 */
#
# DOT NOT MODIFY THIS FILE !
#

use Dotclear\Helper\L10n;

L10n::$locales['WebP images'] = 'Images WebP';
L10n::$locales['Generate and serve WebP versions of the blog images'] = 'Génère et sert des versions WebP des images du blog';
L10n::$locales['Settings have been successfully updated.'] = 'Les réglages ont été mis à jour.';
L10n::$locales['This server has no WebP encoder (PHP GD with WebP, or Imagick).'] = 'Ce serveur n\'a pas d\'encodeur WebP (PHP GD avec WebP, ou Imagick).';
L10n::$locales['Converting… %1$s / %2$s files checked'] = 'Conversion… %1$s / %2$s fichiers vérifiés';
L10n::$locales['Finished: %1$s files checked.'] = 'Terminé : %1$s fichiers vérifiés.';
L10n::$locales['Stopped at %1$s / %2$s. Start again to resume.'] = 'Arrêté à %1$s / %2$s. Relancez pour reprendre.';
L10n::$locales['Error: %s'] = 'Erreur : %s';
L10n::$locales['WebP created'] = 'WebP créés';
L10n::$locales['already up to date'] = 'déjà à jour';
L10n::$locales['kept as JPEG/PNG (WebP not smaller)'] = 'gardés en JPEG/PNG (WebP pas plus léger)';
L10n::$locales['too large to convert'] = 'trop grands pour être convertis';
L10n::$locales['errors'] = 'erreurs';
L10n::$locales['skipped'] = 'ignorés';
L10n::$locales['Status'] = 'État';
L10n::$locales['This server has no WebP encoder: PHP GD must be built with WebP support, or the Imagick extension must support WEBP. Nothing can be converted until one is installed.'] = 'Ce serveur n\'a pas d\'encodeur WebP : PHP GD doit être compilé avec WebP, ou l\'extension Imagick doit gérer le format WEBP. Aucune image ne peut être convertie tant que l\'un des deux n\'est pas installé.';
L10n::$locales['Encoder: %s'] = 'Encodeur : %s';
L10n::$locales['%s original JPEG/PNG images'] = '%s images JPEG/PNG originales';
L10n::$locales['%s thumbnails'] = '%s miniatures';
L10n::$locales['%s of them have a WebP copy'] = '%s ont une copie WebP';
L10n::$locales['Those files weigh %1$s as JPEG/PNG and %2$s as WebP (%3$s%% smaller)'] = 'Ces fichiers pèsent %1$s en JPEG/PNG et %2$s en WebP (%3$s %% de moins)';
L10n::$locales['Wrap images in &lt;picture&gt; with a WebP source (the &lt;img&gt; keeps its JPEG/PNG for older browsers)'] = 'Entourer les images d\'un &lt;picture&gt; avec une source WebP (le &lt;img&gt; garde son JPEG/PNG pour les anciens navigateurs)';
L10n::$locales['Point &lt;img&gt; src/srcset straight to the WebP file (no markup change; every current browser reads WebP)'] = 'Faire pointer src/srcset du &lt;img&gt; directement vers le fichier WebP (aucun balisage ajouté ; tous les navigateurs actuels lisent le WebP)';
L10n::$locales['Do not change pages (use this if the web server picks WebP itself, see README)'] = 'Ne pas modifier les pages (si le serveur web choisit lui-même le WebP, voir le README)';
L10n::$locales['Settings'] = 'Réglages';
L10n::$locales['Create a WebP copy of each JPEG/PNG uploaded to the media manager'] = 'Créer une copie WebP de chaque JPEG/PNG envoyé dans le gestionnaire de médias';
L10n::$locales['Also convert the media thumbnails (_t, _s, _m, _o, _sq…), which listings use'] = 'Convertir aussi les miniatures (_t, _s, _m, _o, _sq…), utilisées par les listes de billets';
L10n::$locales['WebP quality (1-100):'] = 'Qualité WebP (1-100) :';
L10n::$locales['80 is a good default for photos. A WebP copy that is not smaller than its original is not kept.'] = '80 convient bien aux photos. Une copie WebP qui n\'est pas plus légère que l\'original n\'est pas conservée.';
L10n::$locales['Serving the WebP files on the blog'] = 'Diffusion des fichiers WebP sur le blog';
L10n::$locales['Only images under the blog\'s public folder that have a WebP copy are changed. og:image, twitter:image and JSON-LD keep the JPEG. Add class="no-webp" or data-no-webp to an image to leave it alone.'] = 'Seules les images du dossier public du blog qui ont une copie WebP sont modifiées. og:image, twitter:image et le JSON-LD gardent le JPEG. Ajoutez class="no-webp" ou data-no-webp à une image pour qu\'elle ne soit pas modifiée.';
L10n::$locales['Save'] = 'Enregistrer';
L10n::$locales['Convert existing images'] = 'Convertir les images existantes';
L10n::$locales['Goes through the whole public folder in small steps. Images that already have an up-to-date WebP copy are skipped, so it can be stopped and started again at any time.'] = 'Parcourt tout le dossier public par petites étapes. Les images qui ont déjà une copie WebP à jour sont ignorées : vous pouvez arrêter et relancer à tout moment.';
L10n::$locales['Re-create every WebP copy (after changing the quality)'] = 'Recréer toutes les copies WebP (après un changement de qualité)';
L10n::$locales['Start'] = 'Lancer';
L10n::$locales['Stop'] = 'Arrêter';
L10n::$locales['Files not converted'] = 'Fichiers non convertis';
