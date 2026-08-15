<?php

/**
 * Procedural helper functions for WP Vite
 *
 * These are thin wrappers around AssetManager. Use whichever style you prefer:
 *
 *   // Procedural (global functions):
 *   wp_vite_enqueue( __DIR__ . '/dist', 'src/main.tsx', [ 'handle' => 'my-plugin' ] );
 *
 *   // OOP (static calls):
 *   \WpVite\AssetManager::enqueue( __DIR__ . '/dist', 'src/main.tsx', [ 'handle' => 'my-plugin' ] );
 *
 * @package WpVite
 */

declare(strict_types=1);

use WpVite\AssetManager;

if (! function_exists('wp_vite_enqueue')) {
    /**
     * Register and immediately enqueue a Vite entry-point.
     *
     * @param string $buildDir  Absolute path to the build / dist directory.
     * @param string $entry     Entry file as defined in vite.config.js (e.g. 'src/main.tsx').
     * @param array  $options {
     *     @type string   $handle           Required. WordPress script handle.
     *     @type string[] $dependencies     Script dependencies. Default [].
     *     @type string[] $css-dependencies Style dependencies. Default [].
     *     @type string   $css-media        CSS media attribute. Default 'all'.
     *     @type bool     $css-only         Load CSS only (no JS). Default false.
     *     @type bool     $in-footer        Load script in footer. Default false.
     * }
     *
     * @return bool True on success, false on failure.
     */
    function wp_vite_enqueue(string $buildDir, string $entry, array $options = []): bool
    {
        return AssetManager::enqueue($buildDir, $entry, $options);
    }
}

if (! function_exists('wp_vite_register')) {
    /**
     * Register a Vite entry-point without enqueueing.
     *
     * Returns an array of handles you can enqueue later via wp_enqueue_script()
     * and wp_enqueue_style(), or null if the manifest cannot be found.
     *
     * @param string $buildDir  Absolute path to the build / dist directory.
     * @param string $entry     Entry file as defined in vite.config.js (e.g. 'src/main.tsx').
     * @param array  $options   Same options as wp_vite_enqueue().
     *
     * @return array{scripts: string[], styles: string[]}|null
     */
    function wp_vite_register(string $buildDir, string $entry, array $options = []): ?array
    {
        return AssetManager::register($buildDir, $entry, $options);
    }
}
