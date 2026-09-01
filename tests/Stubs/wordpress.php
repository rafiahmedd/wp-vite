<?php

/**
 * WordPress stubs for unit testing WP Vite.
 *
 * These mirror the real WordPress signatures just enough for our tests to run
 * without bootstrapping a full WordPress installation.
 *
 * @package WpVite\Tests
 */

declare(strict_types=1);

// ─────────────────────────────────────────────────────────────────────────────
// Global state (simulates WP's internal registries)
// ─────────────────────────────────────────────────────────────────────────────

/** @internal */
function _wp_reset_stubs(): void {
	global $wp_scripts, $wp_styles, $wp_filters, $wp_inline_scripts, $wp_script_attrs, $wp_debug;

	$wp_scripts        = array();
	$wp_styles         = array();
	$wp_filters        = array();
	$wp_inline_scripts = array();
	$wp_script_attrs   = array();
	$wp_debug          = false;
}

_wp_reset_stubs();

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', '/var/www/html/wp-content' );
}

if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

// ─────────────────────────────────────────────────────────────────────────────
// Path / URL helpers
// ─────────────────────────────────────────────────────────────────────────────

function wp_normalize_path( string $path ): string {
	return str_replace( '\\', '/', $path );
}

function untrailingslashit( string $str ): string {
	return rtrim( $str, '/\\' );
}

function trailingslashit( string $str ): string {
	return untrailingslashit( $str ) . '/';
}

function content_url( string $path = '' ): string {
	return 'https://example.com/wp-content' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
}

// ─────────────────────────────────────────────────────────────────────────────
// JSON helper
// ─────────────────────────────────────────────────────────────────────────────

function wp_json_file_decode( string $filename, array $options = array() ): mixed {
	if ( ! is_file( $filename ) || ! is_readable( $filename ) ) {
		return false;
	}

	$content = file_get_contents( $filename );
	if ( $content === false ) {
		return false;
	}

	$data = json_decode( $content, false, 512, JSON_THROW_ON_ERROR );

	return $data ?? false;
}

function wp_json_encode( mixed $data, int $flags = 0, int $depth = 512 ): string|false {
	return json_encode( $data, $flags, $depth );
}

function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

// ─────────────────────────────────────────────────────────────────────────────
// Script / style registry
// ─────────────────────────────────────────────────────────────────────────────

function wp_register_script(
	string $handle,
	string|false $src,
	array $deps = array(),
	string|bool|null $ver = false,
	bool|array $args = false,
): bool {
	global $wp_scripts;
	$wp_scripts[ $handle ] = compact( 'src', 'deps', 'ver', 'args' );
	return true;
}

function wp_register_style(
	string $handle,
	string|false $src,
	array $deps = array(),
	string|bool|null $ver = false,
	string $media = 'all',
): bool {
	global $wp_styles;
	$wp_styles[ $handle ] = compact( 'src', 'deps', 'ver', 'media' );
	return true;
}

function wp_enqueue_script( string $handle ): void {}
function wp_enqueue_style( string $handle ): void {}

function wp_script_is( string $handle, string $list = 'enqueued' ): bool {
	global $wp_scripts;
	return isset( $wp_scripts[ $handle ] );
}

function wp_style_is( string $handle, string $list = 'enqueued' ): bool {
	global $wp_styles;
	return isset( $wp_styles[ $handle ] );
}

function wp_add_inline_script( string $handle, string $data, string $position = 'after' ): bool {
	global $wp_inline_scripts;
	$wp_inline_scripts[] = compact( 'handle', 'data', 'position' );
	return true;
}

function wp_parse_args( array|string $args, array $defaults = array() ): array {
	if ( is_string( $args ) ) {
		parse_str( $args, $args );
	}

	return array_merge( $defaults, (array) $args );
}

// ─────────────────────────────────────────────────────────────────────────────
// Hook system (minimal)
// ─────────────────────────────────────────────────────────────────────────────

function add_filter( string $tag, callable $callback, int $priority = 10, int $accepted_args = 1 ): true {
	global $wp_filters;
	$wp_filters[ $tag ][] = compact( 'callback', 'priority', 'accepted_args' );
	return true;
}

function add_action( string $tag, callable $callback, int $priority = 10, int $accepted_args = 1 ): true {
	return add_filter( $tag, $callback, $priority, $accepted_args );
}

function apply_filters( string $tag, mixed $value, mixed ...$args ): mixed {
	global $wp_filters;

	if ( empty( $wp_filters[ $tag ] ) ) {
		return $value;
	}

	usort( $wp_filters[ $tag ], fn ( $a, $b ) => $a['priority'] <=> $b['priority'] );

	foreach ( $wp_filters[ $tag ] as $hook ) {
		$value = call_user_func( $hook['callback'], $value, ...$args );
	}

	return $value;
}

function wp_die( string $message = '', string $title = '', array $args = array() ): never {
	throw new \RuntimeException( $message );
}

// ─────────────────────────────────────────────────────────────────────────────
// WP_HTML_Tag_Processor stub (WordPress 6.2+)
// ─────────────────────────────────────────────────────────────────────────────

if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
	class WP_HTML_Tag_Processor {

		private string $html;
		private string $currentSrc = '';
		/** @var array<string, string> */
		private array $pendingAttrs = array();

		public function __construct( string $html ) {
			$this->html = $html;
		}

		public function next_tag( string|array|null $query = null ): bool {
			// Minimal: always returns true for our tests.
			return true;
		}

		public function get_attribute( string $name ): string|null {
			if ( $name === 'src' ) {
				// Extract src from the HTML for comparison.
				if ( preg_match( '/\bsrc=["\']([^"\']+)["\']/', $this->html, $m ) ) {
					return $m[1];
				}
			}

			return null;
		}

		public function set_attribute( string $name, string $value ): void {
			$this->pendingAttrs[ $name ] = $value;
		}

		public function get_updated_html(): string {
			$html = $this->html;

			foreach ( $this->pendingAttrs as $attr => $value ) {
				// Add attribute before the closing > of the first <script tag.
				$html = preg_replace(
					'/(<script\b[^>]*)>/i',
					'$1 ' . $attr . '="' . htmlspecialchars( $value, ENT_QUOTES ) . '">',
					$html,
					1,
				) ?? $html;
			}

			return $html;
		}
	}
}
