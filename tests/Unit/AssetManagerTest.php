<?php

/**
 * Tests for WpVite\AssetManager
 *
 * @package WpVite\Tests\Unit
 */

declare(strict_types=1);

namespace WpVite\Tests\Unit;

use WpVite\AssetManager;
use WpVite\DevServer;
use WpVite\Tests\WpViteTestCase;

/**
 * @covers \WpVite\AssetManager
 * @uses   \WpVite\Manifest
 * @uses   \WpVite\AssetOptions
 * @uses   \WpVite\DevServer
 * @uses   \WpVite\ScriptModuleFilter
 * @uses   \WpVite\UrlResolver
 */
class AssetManagerTest extends WpViteTestCase {

	// ─────────────────────────────────────────────────────────────────────────
	// Development mode
	// ─────────────────────────────────────────────────────────────────────────

	public function test_register_in_dev_mode_registers_vite_client(): void {
		global $wp_scripts;

		$dir = $this->makeDevBuildDir();

		AssetManager::register( $dir, 'src/main.ts', array( 'handle' => 'my-plugin' ) );

		$this->assertArrayHasKey( DevServer::CLIENT_HANDLE, $wp_scripts );
	}

	public function test_register_in_dev_mode_registers_entry_script(): void {
		global $wp_scripts;

		$dir = $this->makeDevBuildDir(
			array(
				'origin' => 'http://localhost:5173',
				'base'   => '/',
			)
		);

		AssetManager::register( $dir, 'src/main.ts', array( 'handle' => 'my-plugin' ) );

		$this->assertArrayHasKey( 'my-plugin', $wp_scripts );
		$this->assertStringContainsString( 'src/main.ts', $wp_scripts['my-plugin']['src'] );
	}

	public function test_register_dev_assets_includes_client_in_deps(): void {
		global $wp_scripts;

		$dir = $this->makeDevBuildDir();

		AssetManager::register(
			$dir,
			'src/main.ts',
			array(
				'handle'       => 'my-plugin',
				'dependencies' => array( 'wp-element' ),
			)
		);

		$deps = $wp_scripts['my-plugin']['deps'];
		$this->assertContains( DevServer::CLIENT_HANDLE, $deps );
		$this->assertContains( 'wp-element', $deps );
	}

	public function test_register_dev_returns_correct_handles(): void {
		$dir = $this->makeDevBuildDir();

		$assets = AssetManager::register( $dir, 'src/main.ts', array( 'handle' => 'my-plugin' ) );

		$this->assertIsArray( $assets );
		$this->assertContains( 'my-plugin', $assets['scripts'] );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Production mode
	// ─────────────────────────────────────────────────────────────────────────

	public function test_register_in_prod_mode_registers_main_script(): void {
		global $wp_scripts;

		$dir = $this->makeProdBuildDir();

		AssetManager::register( $dir, 'src/main.ts', array( 'handle' => 'my-plugin' ) );

		$this->assertArrayHasKey( 'my-plugin', $wp_scripts );
		$this->assertStringContainsString( 'main-Abc123.js', $wp_scripts['my-plugin']['src'] );
	}

	public function test_register_in_prod_mode_registers_css(): void {
		global $wp_styles;

		$dir = $this->makeProdBuildDir();

		$assets = AssetManager::register( $dir, 'src/main.ts', array( 'handle' => 'my-plugin' ) );

		$this->assertNotEmpty( $assets['styles'] );
		$this->assertNotEmpty( $wp_styles );

		$styleHandle = $assets['styles'][0];
		$this->assertArrayHasKey( $styleHandle, $wp_styles );
		$this->assertStringContainsString( 'main-Def456.css', $wp_styles[ $styleHandle ]['src'] );
	}

	public function test_css_only_option_skips_script_registration(): void {
		global $wp_scripts;

		$dir = $this->makeProdBuildDir();

		$assets = AssetManager::register(
			$dir,
			'src/main.ts',
			array(
				'handle'   => 'my-plugin-css',
				'css-only' => true,
			)
		);

		$this->assertEmpty( $assets['scripts'] );
		$this->assertArrayNotHasKey( 'my-plugin-css', $wp_scripts );
		$this->assertNotEmpty( $assets['styles'] );
	}

	public function test_css_dependencies_applied_to_styles(): void {
		global $wp_styles;

		$dir = $this->makeProdBuildDir();

		AssetManager::register(
			$dir,
			'src/main.ts',
			array(
				'handle'           => 'my-plugin',
				'css-dependencies' => array( 'wp-components' ),
			)
		);

		$styleHandle = array_key_first( $wp_styles );
		$this->assertContains( 'wp-components', $wp_styles[ $styleHandle ]['deps'] );
	}

	public function test_css_media_applied_to_styles(): void {
		global $wp_styles;

		$dir = $this->makeProdBuildDir();

		AssetManager::register(
			$dir,
			'src/main.ts',
			array(
				'handle'    => 'my-plugin',
				'css-media' => 'print',
			)
		);

		$styleHandle = array_key_first( $wp_styles );
		$this->assertSame( 'print', $wp_styles[ $styleHandle ]['media'] );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Recursive CSS from dynamic imports
	// ─────────────────────────────────────────────────────────────────────────

	public function test_css_from_nested_dynamic_imports_is_registered(): void {
		global $wp_styles;

		$dir = $this->makeBuildDir(
			array(
				'src/main.ts' => array(
					'file'    => 'assets/main.js',
					'isEntry' => true,
					'imports' => array( '_chunk-A.js' ),
				),
				'_chunk-A.js' => array(
					'file'    => 'assets/chunk-A.js',
					'imports' => array( '_chunk-B.js' ),
					'css'     => array( 'assets/chunk-A.css' ),
				),
				'_chunk-B.js' => array(
					'file' => 'assets/chunk-B.js',
					'css'  => array( 'assets/chunk-B.css' ),
				),
			),
			'.vite/manifest.json'
		);

		$assets = AssetManager::register( $dir, 'src/main.ts', array( 'handle' => 'my-plugin' ) );

		$registeredCss = array_map(
			fn ( $h ) => $wp_styles[ $h ]['src'],
			$assets['styles'],
		);

		$this->assertTrue(
			collect_contains_partial( $registeredCss, 'chunk-A.css' ),
			'chunk-A.css should be registered',
		);
		$this->assertTrue(
			collect_contains_partial( $registeredCss, 'chunk-B.css' ),
			'chunk-B.css from nested import should be registered',
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Missing entry
	// ─────────────────────────────────────────────────────────────────────────

	public function test_returns_null_for_missing_prod_entry(): void {
		$dir = $this->makeProdBuildDir();

		$assets = AssetManager::register( $dir, 'src/nonexistent.ts', array( 'handle' => 'my-plugin' ) );

		$this->assertNull( $assets );
	}

	public function test_returns_null_when_no_manifest_found(): void {
		$assets = AssetManager::register(
			'/tmp/wp-vite-no-manifest-' . uniqid(),
			'src/main.ts',
			array( 'handle' => 'my-plugin' ),
		);

		$this->assertNull( $assets );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// enqueue()
	// ─────────────────────────────────────────────────────────────────────────

	public function test_enqueue_returns_true_on_success(): void {
		$dir    = $this->makeProdBuildDir();
		$result = AssetManager::enqueue( $dir, 'src/main.ts', array( 'handle' => 'my-plugin' ) );

		$this->assertTrue( $result );
	}

	public function test_enqueue_returns_false_when_manifest_missing(): void {
		$result = AssetManager::enqueue(
			'/tmp/wp-vite-no-manifest-' . uniqid(),
			'src/main.ts',
			array( 'handle' => 'my-plugin' ),
		);

		$this->assertFalse( $result );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Filters
	// ─────────────────────────────────────────────────────────────────────────

	public function test_wp_vite_registered_assets_filter_is_applied(): void {
		$dir   = $this->makeProdBuildDir();
		$extra = 'injected-handle';

		add_filter(
			'wp_vite_registered_assets',
			function ( array $assets ) use ( $extra ): array {
				$assets['scripts'][] = $extra;
				return $assets;
			}
		);

		$assets = AssetManager::register( $dir, 'src/main.ts', array( 'handle' => 'my-plugin' ) );

		$this->assertContains( $extra, $assets['scripts'] );
	}

	public function test_wp_vite_prod_assets_filter_is_applied(): void {
		$dir   = $this->makeProdBuildDir();
		$extra = 'prod-extra-handle';

		add_filter(
			'wp_vite_prod_assets',
			function ( array $assets ) use ( $extra ): array {
				$assets['styles'][] = $extra;
				return $assets;
			}
		);

		$assets = AssetManager::register( $dir, 'src/main.ts', array( 'handle' => 'my-plugin' ) );

		$this->assertContains( $extra, $assets['styles'] );
	}

	public function test_wp_vite_dev_assets_filter_is_applied(): void {
		$dir   = $this->makeDevBuildDir();
		$extra = 'dev-extra-handle';

		add_filter(
			'wp_vite_dev_assets',
			function ( array $assets ) use ( $extra ): array {
				$assets['scripts'][] = $extra;
				return $assets;
			}
		);

		$assets = AssetManager::register( $dir, 'src/main.ts', array( 'handle' => 'my-plugin' ) );

		$this->assertContains( $extra, $assets['scripts'] );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// in-footer option
	// ─────────────────────────────────────────────────────────────────────────

	public function test_in_footer_option_passed_to_wp_register_script(): void {
		global $wp_scripts;

		$dir = $this->makeProdBuildDir();

		AssetManager::register(
			$dir,
			'src/main.ts',
			array(
				'handle'    => 'my-plugin',
				'in-footer' => true,
			)
		);

		$this->assertTrue( (bool) $wp_scripts['my-plugin']['args'] );
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// Test helper
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Check if any string in a collection contains the given needle.
 *
 * @param string[] $collection
 */
function collect_contains_partial( array $collection, string $needle ): bool {
	foreach ( $collection as $item ) {
		if ( str_contains( $item, $needle ) ) {
			return true;
		}
	}

	return false;
}
