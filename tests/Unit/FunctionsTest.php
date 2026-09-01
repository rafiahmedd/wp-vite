<?php

/**
 * Tests for the procedural helper functions in src/functions.php
 *
 * @package WpVite\Tests\Unit
 */

declare(strict_types=1);

namespace WpVite\Tests\Unit;

use WpVite\Tests\WpViteTestCase;

/**
 * @covers ::wp_vite_enqueue
 * @covers ::wp_vite_register
 */
class FunctionsTest extends WpViteTestCase {

	// ─────────────────────────────────────────────────────────────────────────
	// wp_vite_register()
	// ─────────────────────────────────────────────────────────────────────────

	public function test_wp_vite_register_returns_array_on_success(): void {
		$dir    = $this->makeProdBuildDir();
		$assets = wp_vite_register( $dir, 'src/main.ts', array( 'handle' => 'my-plugin' ) );

		$this->assertIsArray( $assets );
		$this->assertArrayHasKey( 'scripts', $assets );
		$this->assertArrayHasKey( 'styles', $assets );
	}

	public function test_wp_vite_register_returns_null_on_missing_manifest(): void {
		$assets = wp_vite_register( '/tmp/wp-vite-missing-' . uniqid(), 'src/main.ts', array( 'handle' => 'x' ) );

		$this->assertNull( $assets );
	}

	public function test_wp_vite_register_dev_mode(): void {
		global $wp_scripts;

		$dir = $this->makeDevBuildDir();
		wp_vite_register( $dir, 'src/main.ts', array( 'handle' => 'my-plugin' ) );

		$this->assertArrayHasKey( 'my-plugin', $wp_scripts );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// wp_vite_enqueue()
	// ─────────────────────────────────────────────────────────────────────────

	public function test_wp_vite_enqueue_returns_true_on_success(): void {
		$dir    = $this->makeProdBuildDir();
		$result = wp_vite_enqueue( $dir, 'src/main.ts', array( 'handle' => 'my-plugin' ) );

		$this->assertTrue( $result );
	}

	public function test_wp_vite_enqueue_returns_false_on_missing_manifest(): void {
		$result = wp_vite_enqueue( '/tmp/wp-vite-missing-' . uniqid(), 'src/main.ts', array( 'handle' => 'x' ) );

		$this->assertFalse( $result );
	}

	public function test_wp_vite_enqueue_default_options(): void {
		$dir = $this->makeProdBuildDir();
		// Should not throw when options are omitted entirely.
		$result = wp_vite_enqueue( $dir, 'src/main.ts' );

		$this->assertIsBool( $result );
	}
}
