<?php
/**
 * Procedural helper functions for WP Vite — OPT-IN, not autoloaded.
 *
 * Load them once, early, from your plugin bootstrap:
 *
 *   require_once __DIR__ . '/vendor/autoload.php';
 *   \WpVite\AssetManager::helpers();
 *
 *   wp_vite_enqueue( __DIR__ . '/dist', 'src/main.tsx', [ 'handle' => 'my-plugin' ] );
 *
 * WHY THIS IS NOT IN composer.json's `autoload.files`
 *
 * Composer keys every `files` entry by md5( "<package-name>:<relative-path>" )
 * and records loaded entries in $GLOBALS['__composer_autoload_files'], which is
 * shared by every autoloader in the PHP process. Two WordPress plugins that both
 * vendor this package therefore produce the SAME key: the first plugin to boot
 * requires its copy and flags the key, and every later plugin's autoloader skips
 * its own copy entirely. Those plugins get a fatal "Call to undefined function
 * wp_vite_enqueue()" — and prefixing the vendored copy (php-scoper and friends)
 * does not help, because the key is derived from the package name, not the
 * rewritten contents.
 *
 * Nothing inside a shared package can make `autoload.files` safe, so the globals
 * are opt-in instead. `AssetManager::enqueue()` is PSR-4 and has never had the
 * problem — prefer it.
 *
 * @package WpVite
 */

declare(strict_types=1);

use WpVite\AssetManager;

if ( ! function_exists( 'wp_vite_enqueue' ) ) {
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
	function wp_vite_enqueue( string $buildDir, string $entry, array $options = array() ): bool {
		return AssetManager::enqueue( $buildDir, $entry, $options );
	}
}//end if

if ( ! function_exists( 'wp_vite_register' ) ) {
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
	function wp_vite_register( string $buildDir, string $entry, array $options = array() ): ?array {
		return AssetManager::register( $buildDir, $entry, $options );
	}
}
