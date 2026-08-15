<?php

/**
 * Tests for WpVite\DevServer
 *
 * @package WpVite\Tests\Unit
 */

declare(strict_types=1);

namespace WpVite\Tests\Unit;

use WpVite\DevServer;
use WpVite\Manifest;
use WpVite\Tests\WpViteTestCase;

/**
 * @covers \WpVite\DevServer
 */
class DevServerTest extends WpViteTestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // registerClient()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_registers_vite_client_script(): void
    {
        global $wp_scripts;

        $dir      = $this->makeDevBuildDir(['origin' => 'http://localhost:5173', 'base' => '/']);
        $manifest = Manifest::load($dir);

        DevServer::registerClient($manifest);

        $this->assertArrayHasKey(DevServer::CLIENT_HANDLE, $wp_scripts);
        $this->assertStringContainsString('@vite/client', $wp_scripts[DevServer::CLIENT_HANDLE]['src']);
        $this->assertStringContainsString('localhost:5173', $wp_scripts[DevServer::CLIENT_HANDLE]['src']);
    }

    public function test_registers_client_only_once(): void
    {
        global $wp_scripts;

        $dir      = $this->makeDevBuildDir();
        $manifest = Manifest::load($dir);

        DevServer::registerClient($manifest);
        DevServer::registerClient($manifest); // second call — should no-op

        // The script should appear only once in the registry.
        $handles = array_keys($wp_scripts);
        $count   = count(array_filter($handles, fn ($h) => $h === DevServer::CLIENT_HANDLE));

        $this->assertSame(1, $count);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // buildUrl()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_buildUrl_combines_origin_base_entry(): void
    {
        $dir = $this->makeDevBuildDir([
            'origin' => 'http://localhost:5173',
            'base'   => '/my-plugin',
        ]);

        $manifest = Manifest::load($dir);
        $url      = DevServer::buildUrl($manifest, 'src/main.ts');

        $this->assertSame('http://localhost:5173/my-plugin/src/main.ts', $url);
    }

    public function test_buildUrl_handles_root_base(): void
    {
        $dir = $this->makeDevBuildDir([
            'origin' => 'https://mydomain.com:5173',
            'base'   => '/',
        ]);

        $manifest = Manifest::load($dir);
        $url      = DevServer::buildUrl($manifest, '@vite/client');

        $this->assertSame('https://mydomain.com:5173/@vite/client', $url);
    }

    public function test_buildUrl_collapses_duplicate_slashes(): void
    {
        $dir = $this->makeDevBuildDir([
            'origin' => 'http://localhost:5173',
            'base'   => '/base/',
        ]);

        $manifest = Manifest::load($dir);
        $url      = DevServer::buildUrl($manifest, '/src/app.tsx');

        // Should not have double slashes.
        $this->assertStringNotContainsString('//', ltrim(str_replace('http://', '', $url), '/'));
    }

    public function test_buildUrl_with_empty_base(): void
    {
        $dir = $this->makeDevBuildDir([
            'origin' => 'http://localhost:5173',
            'base'   => '',
        ]);

        $manifest = Manifest::load($dir);
        $url      = DevServer::buildUrl($manifest, 'src/main.ts');

        $this->assertSame('http://localhost:5173/src/main.ts', $url);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // React Refresh preamble
    // ─────────────────────────────────────────────────────────────────────────

    public function test_injects_react_refresh_preamble_when_plugin_present(): void
    {
        global $wp_inline_scripts;

        $dir = $this->makeDevBuildDir([
            'origin'  => 'http://localhost:5173',
            'base'    => '/',
            'plugins' => ['vite:react-refresh', 'wp-vite'],
        ]);

        $manifest = Manifest::load($dir);
        DevServer::registerClient($manifest);
        DevServer::maybeInjectReactRefresh($manifest);

        $this->assertNotEmpty($wp_inline_scripts, 'React Refresh preamble should be injected as inline script');

        $preambleScript = $wp_inline_scripts[0];
        $this->assertSame(DevServer::CLIENT_HANDLE, $preambleScript['handle']);
        $this->assertStringContainsString('RefreshRuntime', $preambleScript['data']);
        $this->assertStringContainsString('@react-refresh', $preambleScript['data']);
    }

    public function test_does_not_inject_preamble_when_react_plugin_absent(): void
    {
        global $wp_inline_scripts;

        $dir = $this->makeDevBuildDir([
            'plugins' => ['vite:vue', 'wp-vite'],
        ]);

        $manifest = Manifest::load($dir);
        DevServer::registerClient($manifest);
        DevServer::maybeInjectReactRefresh($manifest);

        $this->assertEmpty($wp_inline_scripts);
    }

    public function test_react_refresh_preamble_injected_only_once(): void
    {
        global $wp_inline_scripts;

        $dir = $this->makeDevBuildDir([
            'plugins' => ['vite:react-refresh'],
        ]);

        $manifest = Manifest::load($dir);
        DevServer::registerClient($manifest);

        DevServer::maybeInjectReactRefresh($manifest);
        DevServer::maybeInjectReactRefresh($manifest); // second call

        $preambles = array_filter(
            $wp_inline_scripts,
            fn ($s) => str_contains($s['data'], 'RefreshRuntime'),
        );

        $this->assertCount(1, $preambles, 'Preamble should only be injected once per request');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CLIENT_HANDLE constant
    // ─────────────────────────────────────────────────────────────────────────

    public function test_client_handle_constant_value(): void
    {
        $this->assertSame('vite-client', DevServer::CLIENT_HANDLE);
    }
}
