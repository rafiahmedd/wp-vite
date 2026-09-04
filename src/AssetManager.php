<?php
/**
 * Asset manager for WP Vite
 *
 * @package WpVite
 */

declare(strict_types=1);

namespace WpVite;

use RuntimeException;

/**
 * Main entry point for loading Vite assets in WordPress.
 *
 * Handles both development (HMR) and production (built manifest) modes.
 *
 * Usage:
 *
 *   // Register only — get back handles you can enqueue later.
 *   $assets = AssetManager::register( __DIR__ . '/dist', 'src/main.tsx', [
 *       'handle'       => 'my-plugin',
 *       'dependencies' => [ 'wp-element' ],
 *   ] );
 *
 *   // Register + enqueue in one call.
 *   AssetManager::enqueue( __DIR__ . '/dist', 'src/main.tsx', [
 *       'handle' => 'my-plugin',
 *   ] );
 */
class AssetManager {

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Define the procedural wrappers wp_vite_enqueue() / wp_vite_register().
	 *
	 * Opt-in because Composer's `autoload.files` cannot be shared safely by two
	 * WordPress plugins in one process — see the header of src/functions.php.
	 * Call this once from your plugin bootstrap if you want the global style;
	 * calling it from several plugins is harmless, the functions guard
	 * themselves with function_exists().
	 */
	public static function helpers(): void {
		require_once __DIR__ . '/functions.php';
	}

	/**
	 * Register a Vite entry-point and return the resulting script/style handles.
	 *
	 * Returns null when the manifest cannot be found (only if WP_DEBUG is off).
	 * In WP_DEBUG mode it dies with an error message.
	 *
	 * @param string $buildDir  Absolute path to the build / dist directory.
	 * @param string $entry     Entry file path as defined in vite.config.js (e.g. 'src/main.tsx').
	 * @param array  $options   See AssetOptions for supported keys.
	 *
	 * @return array{scripts: string[], styles: string[]}|null
	 */
	public static function register( string $buildDir, string $entry, array $options ): ?array {
		try {
			$manifest = Manifest::load( $buildDir );
		} catch ( RuntimeException $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				wp_die( esc_html( $e->getMessage() ) );
			}

			return null;
		}

		$opts = new AssetOptions( $options );

		$assets = $manifest->isDev()
			? self::loadDevAssets( $manifest, $entry, $opts )
			: self::loadProdAssets( $manifest, $entry, $opts );

		/**
		 * Filters all registered WP Vite assets.
		 *
		 * @param array|null  $assets   Registered asset handles, or null on failure.
		 * @param Manifest    $manifest Manifest instance.
		 * @param string      $entry    Entry point path.
		 * @param AssetOptions $opts    Resolved options.
		 */
		return apply_filters( 'wp_vite_registered_assets', $assets, $manifest, $entry, $opts );
	}

	/**
	 * Register a Vite entry-point and immediately enqueue it.
	 *
	 * @param string $buildDir  Absolute path to the build / dist directory.
	 * @param string $entry     Entry file path as defined in vite.config.js.
	 * @param array  $options   See AssetOptions for supported keys.
	 *
	 * @return bool True on success, false if no assets could be registered.
	 */
	public static function enqueue( string $buildDir, string $entry, array $options ): bool {
		$assets = self::register( $buildDir, $entry, $options );

		if ( $assets === null ) {
			return false;
		}

		foreach ( $assets['scripts'] as $handle ) {
			wp_enqueue_script( $handle );
		}

		foreach ( $assets['styles'] as $handle ) {
			wp_enqueue_style( $handle );
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// Development mode
	// -------------------------------------------------------------------------

	/**
	 * Register assets for the Vite dev server (HMR mode).
	 *
	 * @return array{scripts: string[], styles: string[]}|null
	 */
	private static function loadDevAssets(
		Manifest $manifest,
		string $entry,
		AssetOptions $opts,
	): ?array {
		$clientHandle = DevServer::registerClient( $manifest );
		DevServer::maybeInjectReactRefresh( $manifest );

		$deps = array_merge( array( $clientHandle ), $opts->dependencies );
		$src  = DevServer::buildUrl( $manifest, $entry );

		ScriptModuleFilter::apply( $opts->handle );

		// No version — dev assets must never be cached by the browser.
        // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		if ( ! wp_register_script( $opts->handle, $src, $deps, null, $opts->inFooter ) ) {
			return null;
		}

		$assets = array(
			'scripts' => array( $opts->handle ),
			'styles'  => $opts->cssDependencies,
		// In dev mode Vite injects CSS via JS.
		);

		/**
		 * Filters assets registered in development mode.
		 *
		 * @param array{scripts: string[], styles: string[]} $assets
		 * @param Manifest    $manifest
		 * @param string      $entry
		 * @param AssetOptions $opts
		 */
		return apply_filters( 'wp_vite_dev_assets', $assets, $manifest, $entry, $opts );
	}

	// -------------------------------------------------------------------------
	// Production mode
	// -------------------------------------------------------------------------

	/**
	 * Register assets from the production manifest.
	 *
	 * @return array{scripts: string[], styles: string[]}|null
	 */
	private static function loadProdAssets(
		Manifest $manifest,
		string $entry,
		AssetOptions $opts,
	): ?array {
		$data = $manifest->getData();

		if ( ! isset( $data->{$entry} ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				wp_die(
					esc_html(
						sprintf(
							'[WP Vite] Entry "%s" not found in the production manifest. Did you run `npm run build`?',
							$entry,
						)
					)
				);
			}

			return null;
		}

		$baseUrl = UrlResolver::resolve( $manifest->getDir() );
		$item    = $data->{$entry};

		$assets = array(
			'scripts' => array(),
			'styles'  => array(),
		);

		// ── Main script ──────────────────────────────────────────────────────
		if ( ! $opts->cssOnly ) {
			ScriptModuleFilter::apply( $opts->handle );

			// Version is embedded in the filename by Vite — no need to pass one.
            // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			if ( wp_register_script( $opts->handle, "{$baseUrl}/{$item->file}", $opts->dependencies, null, $opts->inFooter ) ) {
				$assets['scripts'][] = $opts->handle;
			}
		}

		// ── CSS from dynamic imports (recursive) ──────────────────────────
		if ( ! empty( $item->imports ) ) {
			self::processImports( (array) $item->imports, $data, $baseUrl, $opts, $assets );
		}

		// ── CSS directly linked to this entry ─────────────────────────────
		if ( ! empty( $item->css ) ) {
			self::registerStylesheets( (array) $item->css, $baseUrl, $opts, $assets );
		}

		/**
		 * Filters assets registered in production mode.
		 *
		 * @param array{scripts: string[], styles: string[]} $assets
		 * @param Manifest    $manifest
		 * @param string      $entry
		 * @param AssetOptions $opts
		 */
		return apply_filters( 'wp_vite_prod_assets', $assets, $manifest, $entry, $opts );
	}

	/**
	 * Recursively walk imported chunks and collect any CSS they reference.
	 *
	 * @param string[]                                   $imports
	 * @param array{scripts: string[], styles: string[]} $assets Passed by reference.
	 */
	private static function processImports(
		array $imports,
		object $data,
		string $baseUrl,
		AssetOptions $opts,
		array &$assets,
	): void {
		foreach ( $imports as $import ) {
			if ( ! isset( $data->{$import} ) ) {
				continue;
			}

			$chunk = $data->{$import};

			// Recurse deeper first.
			if ( ! empty( $chunk->imports ) ) {
				self::processImports( (array) $chunk->imports, $data, $baseUrl, $opts, $assets );
			}

			if ( ! empty( $chunk->css ) ) {
				self::registerStylesheets( (array) $chunk->css, $baseUrl, $opts, $assets );
			}
		}
	}

	/**
	 * Register a list of CSS files as WordPress styles and record their handles.
	 *
	 * @param string[]                                   $cssFiles
	 * @param array{scripts: string[], styles: string[]} $assets Passed by reference.
	 */
	private static function registerStylesheets(
		array $cssFiles,
		string $baseUrl,
		AssetOptions $opts,
		array &$assets,
	): void {
		foreach ( $cssFiles as $cssFile ) {
			$slug   = self::toSlug( pathinfo( $cssFile, PATHINFO_FILENAME ) );
			$handle = "{$opts->handle}-{$slug}";
			$url    = "{$baseUrl}/{$cssFile}";

			// Version is embedded in the filename — no need for a separate version string.
            // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			if ( wp_register_style( $handle, $url, $opts->cssDependencies, null, $opts->cssMedia ) ) {
				$assets['styles'][] = $handle;
			}
		}
	}

	/**
	 * Convert a filename to a WordPress-safe handle slug.
	 */
	private static function toSlug( string $name ): string {
		return strtolower( trim( (string) preg_replace( '/[^A-Za-z0-9]+/', '-', $name ), '-' ) );
	}
}
