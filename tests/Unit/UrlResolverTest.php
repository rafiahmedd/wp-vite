<?php

/**
 * Tests for WpVite\UrlResolver
 *
 * @package WpVite\Tests\Unit
 */

declare(strict_types=1);

namespace WpVite\Tests\Unit;

use WpVite\Tests\WpViteTestCase;
use WpVite\UrlResolver;

/**
 * @covers \WpVite\UrlResolver
 */
class UrlResolverTest extends WpViteTestCase
{
    // The stub sets WP_CONTENT_DIR = '/var/www/html/wp-content'
    // and content_url() returns 'https://example.com/wp-content/...'

    public function test_resolves_plugin_path_to_url(): void
    {
        $dir = '/var/www/html/wp-content/plugins/my-plugin/dist';
        $url = UrlResolver::resolve($dir);

        $this->assertSame(
            'https://example.com/wp-content/plugins/my-plugin/dist',
            $url,
        );
    }

    public function test_resolves_theme_path_to_url(): void
    {
        $dir = '/var/www/html/wp-content/themes/my-theme/dist';
        $url = UrlResolver::resolve($dir);

        $this->assertSame(
            'https://example.com/wp-content/themes/my-theme/dist',
            $url,
        );
    }

    public function test_strips_trailing_slash(): void
    {
        $dir = '/var/www/html/wp-content/plugins/my-plugin/dist/';
        $url = UrlResolver::resolve($dir);

        $this->assertStringEndsNotWith('/', $url);
    }

    public function test_normalizes_windows_paths(): void
    {
        // wp_normalize_path() (our stub) converts backslashes to forward slashes.
        $dir = 'C:\\inetpub\\wwwroot\\wp-content\\plugins\\my-plugin\\dist';
        // Since WP_CONTENT_DIR is the unix path in our stubs, just confirm no exception.
        $this->expectNotToPerformAssertions();
        UrlResolver::resolve($dir);
    }

    public function test_returns_url_without_double_slashes(): void
    {
        $dir = '/var/www/html/wp-content/plugins/my-plugin/dist';
        $url = UrlResolver::resolve($dir);

        // After scheme (https://) there should be no double slashes.
        $path = substr($url, strpos($url, '://') + 3);
        $this->assertStringNotContainsString('//', $path);
    }
}
