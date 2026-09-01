<?php
/**
 * Development server utilities for WP Vite
 *
 * @package WpVite
 */

declare(strict_types=1);

namespace WpVite;

/**
 * Manages Vite development server integration:
 *
 * - Registers the `@vite/client` HMR script.
 * - Injects the React Refresh preamble when the react plugin is active.
 * - Builds full dev-server asset URLs from the manifest origin + base.
 */
class DevServer {

	/**
	 * WordPress script handle for the Vite HMR client.
	 */
	public const CLIENT_HANDLE = 'vite-client';

	/**
	 * Whether the React Refresh preamble has already been injected this request.
	 */
	private static bool $reactPreamblePrinted = false;

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Register the Vite HMR client script (@vite/client) if not already registered.
	 */
	public static function registerClient( Manifest $manifest ): void {
		if ( wp_script_is( self::CLIENT_HANDLE, 'registered' ) ) {
			return;
		}

		$src = self::buildUrl( $manifest, '@vite/client' );

		// No version string — this is a live dev asset, never cache it.
        // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_register_script( self::CLIENT_HANDLE, $src, array(), null, false );

		ScriptModuleFilter::apply( self::CLIENT_HANDLE );
	}

	/**
	 * Inject the React Refresh preamble once if the react plugin is active.
	 *
	 * The preamble must run before any component module is imported, so it is
	 * appended as an inline `type="module"` script after the vite-client script.
	 */
	public static function maybeInjectReactRefresh( Manifest $manifest ): void {
		if ( self::$reactPreamblePrinted ) {
			return;
		}

		$plugins = (array) ( $manifest->getData()->plugins ?? array() );

		if ( ! in_array( 'vite:react-refresh', $plugins, true ) ) {
			return;
		}

		$refreshSrc = self::buildUrl( $manifest, '@react-refresh' );
		$position   = 'after';

		// Preamble required by @vitejs/plugin-react for Fast Refresh.
		$preamble = <<<JS
import RefreshRuntime from "{$refreshSrc}";
RefreshRuntime.injectIntoGlobalHook(window);
window.\$RefreshReg$ = () => {};
window.\$RefreshSig$ = () => (type) => type;
window.__vite_plugin_react_preamble_installed__ = true;
JS;

		wp_add_inline_script( self::CLIENT_HANDLE, $preamble, $position );

		// The inline script must also carry type="module".
		add_filter(
			'wp_inline_script_attributes',
			static function ( array $attributes ) use ( $position ): array {
				$targetId = self::CLIENT_HANDLE . "-js-{$position}";

				if ( isset( $attributes['id'] ) && $attributes['id'] === $targetId ) {
					$attributes['type'] = 'module';
				}

				return $attributes;
			},
		);

		self::$reactPreamblePrinted = true;
	}

	/**
	 * Build the full URL for a dev server asset.
	 *
	 * Combines origin + base + entry path, collapsing duplicate slashes.
	 *
	 * @param string $entry Asset path relative to the Vite dev server root (e.g. 'src/main.tsx').
	 */
	public static function buildUrl( Manifest $manifest, string $entry ): string {
		$data   = $manifest->getData();
		$origin = untrailingslashit( (string) ( $data->origin ?? '' ) );
		$base   = trim( (string) ( $data->base ?? '/' ), '/' );
		$entry  = ltrim( $entry, '/' );

		$path = $base !== '' ? "{$base}/{$entry}" : $entry;
		$path = (string) preg_replace( '/\/+/', '/', $path );

		return "{$origin}/{$path}";
	}

	// -------------------------------------------------------------------------
	// Testing helpers
	// -------------------------------------------------------------------------

	/**
	 * Reset static state (useful in unit tests).
	 */
	public static function reset(): void {
		self::$reactPreamblePrinted = false;
	}
}
