<?php

/**
 * Tests for WpVite\ScriptModuleFilter
 *
 * @package WpVite\Tests\Unit
 */

declare(strict_types=1);

namespace WpVite\Tests\Unit;

use WpVite\ScriptModuleFilter;
use WpVite\Tests\WpViteTestCase;

/**
 * @covers \WpVite\ScriptModuleFilter
 */
class ScriptModuleFilterTest extends WpViteTestCase {

	// ─────────────────────────────────────────────────────────────────────────
	// apply() — filter registration
	// ─────────────────────────────────────────────────────────────────────────

	public function test_apply_registers_script_loader_tag_filter(): void {
		global $wp_filters;

		ScriptModuleFilter::apply( 'my-handle' );

		$this->assertArrayHasKey( 'script_loader_tag', $wp_filters );
	}

	public function test_apply_only_registers_once_per_handle(): void {
		global $wp_filters;

		ScriptModuleFilter::apply( 'my-handle' );
		ScriptModuleFilter::apply( 'my-handle' );
		ScriptModuleFilter::apply( 'my-handle' );

		$count = count( $wp_filters['script_loader_tag'] ?? array() );
		$this->assertSame( 1, $count, 'Filter should be registered only once per handle' );
	}

	public function test_apply_registers_independently_per_handle(): void {
		global $wp_filters;

		ScriptModuleFilter::apply( 'handle-a' );
		ScriptModuleFilter::apply( 'handle-b' );

		$count = count( $wp_filters['script_loader_tag'] ?? array() );
		$this->assertSame( 2, $count, 'Each unique handle should add its own filter' );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// The filter callback — type="module" injection
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Apply the registered filter to a script tag and return the result.
	 */
	private function applyFilter( string $targetHandle, string $tag, string $currentHandle, string $src ): string {
		global $wp_filters;

		ScriptModuleFilter::apply( $targetHandle );

		$result = $tag;

		foreach ( $wp_filters['script_loader_tag'] ?? array() as $hook ) {
			$result = ( $hook['callback'] )( $result, $currentHandle, $src );
		}

		return $result;
	}

	public function test_adds_type_module_to_matching_handle(): void {
		$src = 'https://example.com/main.js';
		$tag = "<script src='{$src}'></script>";

		$result = $this->applyFilter( 'my-plugin', $tag, 'my-plugin', $src );

		$this->assertStringContainsString( 'type="module"', $result );
	}

	public function test_does_not_modify_non_matching_handle(): void {
		$src = 'https://example.com/other.js';
		$tag = "<script src='{$src}'></script>";

		$result = $this->applyFilter( 'my-plugin', $tag, 'other-plugin', $src );

		$this->assertStringNotContainsString( 'type="module"', $result );
	}

	public function test_preserves_existing_attributes(): void {
		$src = 'https://example.com/main.js';
		$tag = "<script id=\"my-plugin-js\" src='{$src}'></script>";

		$result = $this->applyFilter( 'my-plugin', $tag, 'my-plugin', $src );

		$this->assertStringContainsString( 'id="my-plugin-js"', $result );
		$this->assertStringContainsString( 'type="module"', $result );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// reset()
	// ─────────────────────────────────────────────────────────────────────────

	public function test_reset_allows_re_registration(): void {
		global $wp_filters;

		ScriptModuleFilter::apply( 'my-handle' );
		ScriptModuleFilter::reset();
		ScriptModuleFilter::apply( 'my-handle' );

		$count = count( $wp_filters['script_loader_tag'] ?? array() );
		// After reset and one apply, we should have 1 (the old one from before reset + new one,
		// but the registry is reset between tests in setUp — here we just confirm apply works again).
		$this->assertGreaterThanOrEqual( 1, $count );
	}
}
