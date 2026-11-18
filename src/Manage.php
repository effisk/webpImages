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
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Process\TraitProcess;
use Exception;
use Throwable;

/**
 * Plugin page: settings, status and batch conversion of existing images.
 *
 * The batch runs as a series of small AJAX POSTs to this page (each one
 * stops after a few seconds), so it never hits PHP's max_execution_time and
 * can be stopped and resumed.
 */
class Manage
{
    use TraitProcess;

    /** Seconds of work per batch request. */
    private const BATCH_SECONDS = 8;

    /** Max files looked at per batch request. */
    private const BATCH_FILES = 400;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::MANAGE));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        // AJAX batch step (the nonce was already checked by Dotclear for this POST)
        if (!empty($_POST['webp_batch'])) {
            self::batchStep();
        }

        if (isset($_POST['webp_save'])) {
            try {
                $ws      = App::blogWorkspace();
                $quality = isset($_POST['webp_quality']) && is_numeric($_POST['webp_quality']) ? (int) $_POST['webp_quality'] : My::DEFAULT_QUALITY;
                $mode    = isset($_POST['webp_mode']) && is_string($_POST['webp_mode']) ? $_POST['webp_mode'] : My::MODE_PICTURE;
                if (!in_array($mode, [My::MODE_PICTURE, My::MODE_REPLACE, My::MODE_OFF], true)) {
                    $mode = My::MODE_PICTURE;
                }

                My::settings()->put('active', !empty($_POST['webp_active']), $ws::NS_BOOL);
                My::settings()->put('thumbnails', !empty($_POST['webp_thumbnails']), $ws::NS_BOOL);
                My::settings()->put('quality', max(1, min(100, $quality)), $ws::NS_INT);
                My::settings()->put('mode', $mode, $ws::NS_STRING);

                // Page HTML changes with the mode: refresh HTTP caches
                App::blog()->triggerBlog();

                App::backend()->notices()->addSuccessNotice(__('Settings have been successfully updated.'));
                My::redirect();
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        }

        return true;
    }

    /**
     * One step of the batch: convert files after the cursor, reply in JSON, exit.
     */
    private static function batchStep(): never
    {
        $reply = [
            'ok'       => true,
            'total'    => 0,
            'position' => 0,
            'after'    => '',
            'done'     => false,
            'counts'   => [],
            'errors'   => [],
        ];

        try {
            $config = My::config();
            $force  = !empty($_POST['force']);
            $after  = isset($_POST['after']) && is_string($_POST['after']) ? $_POST['after'] : '';
            $root   = App::blog()->publicPath();

            if (Converter::engine() === '') {
                throw new Exception(__('This server has no WebP encoder (PHP GD with WebP, or Imagick).'));
            }

            $files          = Converter::scan($root, $config['thumbnails']);
            $reply['total'] = count($files);

            // First file strictly after the cursor (list is sorted)
            $start = 0;
            if ($after !== '') {
                $lo = 0;
                $hi = count($files);
                while ($lo < $hi) {
                    $mid = intdiv($lo + $hi, 2);
                    if (strcmp($files[$mid], $after) <= 0) {
                        $lo = $mid + 1;
                    } else {
                        $hi = $mid;
                    }
                }
                $start = $lo;
            }

            $began  = microtime(true);
            $counts = [];
            $i      = $start;
            for ($n = 0; $i < count($files) && $n < self::BATCH_FILES; $i++, $n++) {
                $status          = Converter::convert($root . '/' . $files[$i], $config['quality'], $force);
                $counts[$status] = ($counts[$status] ?? 0) + 1;
                if ($status === Converter::STATUS_ERROR || $status === Converter::STATUS_TOOLARGE) {
                    $reply['errors'][] = ['file' => $files[$i], 'message' => Converter::$lastError];
                }
                $reply['after'] = $files[$i];

                if (microtime(true) - $began > self::BATCH_SECONDS) {
                    $i++;

                    break;
                }
            }

            $reply['counts']   = $counts;
            $reply['position'] = $i;
            $reply['done']     = $i >= count($files);

            if ($reply['done']) {
                // New WebP files change the pages: refresh HTTP caches
                App::blog()->triggerBlog();
            }
        } catch (Throwable $e) {
            $reply['ok']      = false;
            $reply['message'] = $e->getMessage();
        }

        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($reply, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * @return array{originals: int, thumbnails: int, with_webp: int, source_bytes: int, webp_bytes: int}
     */
    private static function stats(): array
    {
        $stats = ['originals' => 0, 'thumbnails' => 0, 'with_webp' => 0, 'source_bytes' => 0, 'webp_bytes' => 0];
        $root  = App::blog()->publicPath();

        foreach (Converter::scan($root, true) as $relative) {
            if (str_starts_with(basename($relative), '.')) {
                $stats['thumbnails']++;
            } else {
                $stats['originals']++;
            }

            $webp = Converter::webpPathFor($root . '/' . $relative);
            if (is_file($webp)) {
                $stats['with_webp']++;
                $stats['source_bytes'] += (int) @filesize($root . '/' . $relative);
                $stats['webp_bytes'] += (int) @filesize($webp);
            }
        }

        return $stats;
    }

    public static function render(): void
    {
        $config = My::config();
        $engine = Converter::engine();
        $stats  = self::stats();

        $head = App::backend()->page()->jsJson('webp-batch', [
            'url'      => My::manageUrl([], '&'),
            'running'  => __('Converting… %1$s / %2$s files checked'),
            'finished' => __('Finished: %1$s files checked.'),
            'stopped'  => __('Stopped at %1$s / %2$s. Start again to resume.'),
            'failed'   => __('Error: %s'),
            'labels'   => [
                Converter::STATUS_CREATED  => __('WebP created'),
                Converter::STATUS_UPTODATE => __('already up to date'),
                Converter::STATUS_BIGGER   => __('kept as JPEG/PNG (WebP not smaller)'),
                Converter::STATUS_TOOLARGE => __('too large to convert'),
                Converter::STATUS_ERROR    => __('errors'),
                Converter::STATUS_SKIPPED  => __('skipped'),
            ],
        ]) . My::jsLoad('batch') . My::cssLoad('admin');

        App::backend()->page()->openModule(My::name(), $head);

        echo App::backend()->page()->breadcrumb([
            Html::escapeHTML(App::blog()->name()) => '',
            __('WebP images')                     => '',
        ]);
        echo App::backend()->notices()->getNotices();

        $e = Html::escapeHTML(...);

        // Status
        $saving = $stats['source_bytes'] > 0 ? (1 - $stats['webp_bytes'] / $stats['source_bytes']) * 100 : 0;
        echo '<div class="fieldset"><h3>' . $e(__('Status')) . '</h3>';
        if ($engine === '') {
            echo '<p class="error">' . $e(__('This server has no WebP encoder: PHP GD must be built with WebP support, or the Imagick extension must support WEBP. Nothing can be converted until one is installed.')) . '</p>';
        } else {
            echo '<p>' . sprintf($e(__('Encoder: %s')), '<strong>' . ($engine === 'gd' ? 'PHP GD' : 'Imagick') . '</strong>') . '</p>';
        }
        echo '<ul>'
            . '<li>' . sprintf($e(__('%s original JPEG/PNG images')), '<strong>' . number_format($stats['originals']) . '</strong>') . '</li>'
            . '<li>' . sprintf($e(__('%s thumbnails')), '<strong>' . number_format($stats['thumbnails']) . '</strong>') . '</li>'
            . '<li>' . sprintf($e(__('%s of them have a WebP copy')), '<strong>' . number_format($stats['with_webp']) . '</strong>') . '</li>';
        if ($stats['with_webp'] > 0) {
            echo '<li>' . sprintf(
                $e(__('Those files weigh %1$s as JPEG/PNG and %2$s as WebP (%3$s%% smaller)')),
                self::size($stats['source_bytes']),
                self::size($stats['webp_bytes']),
                number_format($saving, 0)
            ) . '</li>';
        }
        echo '</ul></div>';

        // Settings
        $hidden = '';
        foreach (My::hiddenFields() as $field) {
            $hidden .= $field->render();
        }

        $modes = [
            My::MODE_PICTURE => __('Wrap images in &lt;picture&gt; with a WebP source (the &lt;img&gt; keeps its JPEG/PNG for older browsers)'),
            My::MODE_REPLACE => __('Point &lt;img&gt; src/srcset straight to the WebP file (no markup change; every current browser reads WebP)'),
            My::MODE_OFF     => __('Do not change pages (use this if the web server picks WebP itself, see README)'),
        ];
        $radios = '';
        foreach ($modes as $value => $label) {
            $radios .= '<p><label class="classic"><input type="radio" name="webp_mode" value="' . $value . '"'
                . ($config['mode'] === $value ? ' checked' : '') . '> ' . $label . '</label></p>';
        }

        echo '<form method="post" action="' . App::backend()->getPageURL() . '" id="webp-settings">'
            . '<div class="fieldset"><h3>' . $e(__('Settings')) . '</h3>'
            . '<p><label class="classic"><input type="checkbox" name="webp_active" value="1"' . ($config['active'] ? ' checked' : '') . '> '
            . $e(__('Create a WebP copy of each JPEG/PNG uploaded to the media manager')) . '</label></p>'
            . '<p><label class="classic"><input type="checkbox" name="webp_thumbnails" value="1"' . ($config['thumbnails'] ? ' checked' : '') . '> '
            . $e(__('Also convert the media thumbnails (_t, _s, _m, _o, _sq…), which listings use')) . '</label></p>'
            . '<p><label for="webp_quality">' . $e(__('WebP quality (1-100):')) . '</label> '
            . '<input type="number" id="webp_quality" name="webp_quality" min="1" max="100" value="' . $config['quality'] . '"></p>'
            . '<p class="form-note">' . $e(__('80 is a good default for photos. A WebP copy that is not smaller than its original is not kept.')) . '</p>'
            . '<h4>' . $e(__('Serving the WebP files on the blog')) . '</h4>'
            . $radios
            . '<p class="form-note">' . $e(__('Only images under the blog\'s public folder that have a WebP copy are changed. og:image, twitter:image and JSON-LD keep the JPEG. Add class="no-webp" or data-no-webp to an image to leave it alone.')) . '</p>'
            . '<p class="form-buttons">' . $hidden
            . '<input type="submit" name="webp_save" value="' . $e(__('Save')) . '"></p>'
            . '</div></form>';

        // Batch
        echo '<div class="fieldset" id="webp-batch-box"><h3>' . $e(__('Convert existing images')) . '</h3>'
            . '<p>' . $e(__('Goes through the whole public folder in small steps. Images that already have an up-to-date WebP copy are skipped, so it can be stopped and started again at any time.')) . '</p>'
            . '<p><label class="classic"><input type="checkbox" id="webp-force" value="1"> '
            . $e(__('Re-create every WebP copy (after changing the quality)')) . '</label></p>'
            . '<p><button type="button" class="submit" id="webp-start"' . ($engine === '' ? ' disabled' : '') . '>' . $e(__('Start')) . '</button> '
            . '<button type="button" id="webp-stop" disabled>' . $e(__('Stop')) . '</button></p>'
            . '<p><progress id="webp-progress" max="100" value="0" hidden></progress></p>'
            . '<p id="webp-status" aria-live="polite"></p>'
            . '<div id="webp-counts"></div>'
            . '<details id="webp-errors-box" hidden><summary>' . $e(__('Files not converted')) . '</summary><ul id="webp-errors"></ul></details>'
            . '</div>';

        App::backend()->page()->closeModule();
    }

    private static function size(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return number_format($bytes / 1024 / 1024 / 1024, 2) . ' GB';
        }
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / 1024 / 1024, 1) . ' MB';
        }

        return number_format($bytes / 1024, 0) . ' KB';
    }
}
