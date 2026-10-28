<?php
/**
 * Asset URL resolver for WP Vite
 *
 * @package WpVite
 */

declare(strict_types=1);

namespace WpVite;

/**
 * Converts a filesystem directory path to a publicly accessible WordPress URL.
 *
 * Handles standard and non-standard WordPress installations, including:
 * - Plugins and themes in custom wp-content locations
 * - WordPress installed in a subdirectory
 * - Windows path separators (via wp_normalize_path)
 */
class UrlResolver {

	/**
	 * Resolve the public URL for the given filesystem directory.
	 *
	 * @param string $dir Absolute filesystem path to the asset directory.
	 *
	 * @return string Trailing-slash-free public URL.
	 */
	public static function resolve( string $dir ): string {
		$contentDir = wp_normalize_path( WP_CONTENT_DIR );
		$assetDir   = untrailingslashit( wp_normalize_path( $dir ) );

		// A plugin folder that is a symlink reports its REAL path, outside
		// WP_CONTENT_DIR, so stripping the content dir left a filesystem path in
		// the URL. WordPress maps a symlinked plugin's real path back to its
		// folder (wp_register_plugin_realpath()), and plugins_url() reads that map.
		if ( strpos( $assetDir . '/', trailingslashit( $contentDir ) ) !== 0 && function_exists( 'plugins_url' ) ) {
			$probe = $assetDir . '/index.php';

			if ( plugin_basename( $probe ) !== ltrim( $probe, '/' ) ) {
				return untrailingslashit( plugins_url( '', $probe ) );
			}
		}

		$url = content_url( str_replace( $contentDir, '', $assetDir ) );

		// Fix URLs for non-standard WP installs (e.g. WP in a subdirectory like /wp).
		// Pattern: detect an extra path segment between the domain and wp-content.
		$pattern = '/(?P<address>https?:\/\/[^\/]+\/)(?P<fullPath>wp-content(?P<removable>\/[^\/]+)*\/(plugins|themes|mu-plugins)\/.+)/';

		if ( preg_match( $pattern, $url, $parts ) === 1 ) {
			$url = $parts['address'] . str_replace( $parts['removable'], '', $parts['fullPath'] );
		}

		return untrailingslashit( $url );
	}
}
