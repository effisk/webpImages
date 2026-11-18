<?php

/**
 * @brief webpImages, a plugin for Dotclear 2
 *
 * Generates WebP copies of the blog's JPEG/PNG images (originals and
 * thumbnails) and serves them to browsers.
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright effisk
 * @copyright AGPL-3.0
 */
if (isset($this) && is_object($this) && method_exists($this, 'registerModule') && isset($this->id) && is_string($this->id)) {
    $this->registerModule(
        'WebP images',                                              // Name
        'Generate and serve WebP versions of the blog images',      // Description
        'effisk',                                                   // Author
        '1.0.5',                                                    // Version
        [
            'requires'    => [['core', '2.36']],
            'permissions' => 'My',
            'type'        => 'plugin',
            'settings'    => [
                'self' => '',
            ],
            'details'     => 'https://github.com/effisk/webpImages',
            'support'     => 'https://github.com/effisk/webpImages/issues',
            'repository'  => 'https://raw.githubusercontent.com/effisk/webpImages/main/dcstore.xml',
        ]
    );
}
