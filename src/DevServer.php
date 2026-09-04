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
	 * Base of the WordPress script handle for the Vite HMR client.
	 *
	 * The real handle is this plus a hash of the dev server it points at — see
	 * clientHandle(). WordPress' script registry is one global table shared by
	 * every plugin, so a fixed 'vite-client' means the second plugin to register
	 * silently loses and ends up loading the FIRST plugin's dev server.
	 */
	public const CLIENT_HANDLE = 'vite-client';

	/**
	 * Client handles whose React Refresh preamble has been injected this request.
	 *
	 * @var array<string, true>
	 */
	private static array $reactPreamblePrinted = array();

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * The HMR client handle for a given dev server.
	 *
	 * Derived from origin + base so two plugins running against the SAME dev
	 * server share one @vite/client (correct — the page needs exactly one), while
	 * two plugins running their own dev servers each get their own.
	 */
	public static function clientHandle( Manifest $manifest ): string {
		$data   = $manifest->getData();
		$origin = untrailingslashit( (string) ( $data->origin ?? '' ) );
		$base   = trim( (string) ( $data->base ?? '/' ), '/' );

		return self::CLIENT_HANDLE . '-' . substr( md5( "{$origin}/{$base}" ), 0, 8 );
	}

	/**
	 * Register the Vite HMR client script (@vite/client) if not already registered.
	 *
	 * @return string The handle to depend on.
	 */
	public static function registerClient( Manifest $manifest ): string {
		$handle = self::clientHandle( $manifest );

		if ( wp_script_is( $handle, 'registered' ) ) {
			return $handle;
		}

		$src = self::buildUrl( $manifest, '@vite/client' );

		// No version string — this is a live dev asset, never cache it.
        // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_register_script( $handle, $src, array(), null, false );

		ScriptModuleFilter::apply( $handle );

		return $handle;
	}

	/**
	 * Inject the React Refresh preamble once if the react plugin is active.
	 *
	 * The preamble must run before any component module is imported, so it is
	 * appended as an inline `type="module"` script after the vite-client script.
	 */
	public static function maybeInjectReactRefresh( Manifest $manifest ): void {
		$handle = self::clientHandle( $manifest );

		if ( isset( self::$reactPreamblePrinted[ $handle ] ) ) {
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

		wp_add_inline_script( $handle, $preamble, $position );

		// The inline script must also carry type="module".
		add_filter(
			'wp_inline_script_attributes',
			static function ( array $attributes ) use ( $handle, $position ): array {
				$targetId = "{$handle}-js-{$position}";

				if ( isset( $attributes['id'] ) && $attributes['id'] === $targetId ) {
					$attributes['type'] = 'module';
				}

				return $attributes;
			},
		);

		// ponytail: one preamble per dev server. Two React dev servers on one page
		// still fight over window.__vite_plugin_react_preamble_installed__ — that
		// is a Vite/React Refresh limitation, not something a handle can fix.
		self::$reactPreamblePrinted[ $handle ] = true;
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
		self::$reactPreamblePrinted = array();
	}
}
