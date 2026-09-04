<?php

/**
 * Example: using WP Vite in your WordPress plugin
 *
 * After installing via Composer:
 *   composer require rafiahmedd/wp-vite
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

// ─── Option 1: Procedural (global helper functions) ──────────────────────────
//
// Opt-in: the globals are NOT autoloaded, because Composer's `autoload.files`
// deduplicates by package name across the whole PHP process — so in a site with
// two plugins vendoring this package, only the first one's copy would ever load.
// One call, once, from your bootstrap:

\WpVite\AssetManager::helpers();

add_action('wp_enqueue_scripts', function (): void {

    // Basic usage — enqueue a single entry point.
    wp_vite_enqueue(
        __DIR__ . '/dist',
        'src/main.ts',
        [
            'handle'    => 'my-plugin-frontend',
            'in-footer' => true,
        ]
    );

    // With script and style dependencies.
    wp_vite_enqueue(
        __DIR__ . '/dist',
        'src/app.tsx',
        [
            'handle'           => 'my-plugin-app',
            'dependencies'     => ['wp-element', 'wp-components'],
            'css-dependencies' => ['wp-components'],
            'in-footer'        => true,
        ]
    );

    // CSS-only enqueue (no JS script tag).
    wp_vite_enqueue(
        __DIR__ . '/dist',
        'src/styles.css',
        [
            'handle'   => 'my-plugin-styles',
            'css-only' => true,
        ]
    );

    // Register only — enqueue manually later.
    $assets = wp_vite_register(
        __DIR__ . '/dist',
        'src/sidebar.ts',
        ['handle' => 'my-plugin-sidebar']
    );

    if ($assets) {
        foreach ($assets['scripts'] as $handle) {
            wp_enqueue_script($handle);
        }
        foreach ($assets['styles'] as $handle) {
            wp_enqueue_style($handle);
        }
    }
});

// ─── Option 2: OOP (static facade) ───────────────────────────────────────────
//
// Same behaviour, class-based call for when you prefer namespaced code.

use WpVite\AssetManager;

add_action('admin_enqueue_scripts', function (): void {

    AssetManager::enqueue(
        __DIR__ . '/dist',
        'src/admin.tsx',
        [
            'handle'       => 'my-plugin-admin',
            'dependencies' => ['wp-element', 'wp-api-fetch'],
            'in-footer'    => true,
        ]
    );
});

// ─── Multi-entry: enqueue each entry separately ───────────────────────────────

add_action('wp_enqueue_scripts', function (): void {

    $buildDir = __DIR__ . '/dist';

    $entries = [
        'src/main.ts'    => 'my-plugin-main',
        'src/sidebar.ts' => 'my-plugin-sidebar',
    ];

    foreach ($entries as $entry => $handle) {
        wp_vite_enqueue($buildDir, $entry, [
            'handle'    => $handle,
            'in-footer' => true,
        ]);
    }
});

// ─── Hooks provided by the package ───────────────────────────────────────────
//
// You can filter/extend registered assets using these hooks:
//
//   wp_vite_manifest_data   — Modify raw manifest data after it's read.
//   wp_vite_registered_assets — Alter handles after register() runs.
//   wp_vite_dev_assets      — Alter handles registered in dev mode.
//   wp_vite_prod_assets     — Alter handles registered in prod mode.
//
// Example: add extra inline data to the main script after it's registered.

add_filter('wp_vite_registered_assets', function (array $assets): array {
    if (in_array('my-plugin-app', $assets['scripts'], true)) {
        wp_add_inline_script(
            'my-plugin-app',
            'window.MyPlugin = ' . wp_json_encode(['ajaxUrl' => admin_url('admin-ajax.php')]) . ';',
            'before'
        );
    }

    return $assets;
});
