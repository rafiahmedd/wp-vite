<?php

/**
 * Tests for WpVite\Manifest
 *
 * @package WpVite\Tests\Unit
 */

declare(strict_types=1);

namespace WpVite\Tests\Unit;

use RuntimeException;
use WpVite\Manifest;
use WpVite\Tests\WpViteTestCase;

/**
 * @covers \WpVite\Manifest
 */
class ManifestTest extends WpViteTestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Dev manifest detection
    // ─────────────────────────────────────────────────────────────────────────

    public function test_loads_dev_manifest(): void
    {
        $dir = $this->makeDevBuildDir(['origin' => 'http://localhost:5173']);

        $manifest = Manifest::load($dir);

        $this->assertTrue($manifest->isDev());
        $this->assertSame('http://localhost:5173', $manifest->getData()->origin);
    }

    public function test_dev_manifest_takes_priority_over_prod(): void
    {
        // Write both files in the same directory.
        $dir = $this->makeDevBuildDir();

        // Also write a v4 prod manifest.
        $prodPath = $dir . '/manifest.json';
        file_put_contents($prodPath, json_encode(['src/main.ts' => ['file' => 'main-prod.js']]));
        $this->tempFiles[] = $prodPath;

        $manifest = Manifest::load($dir);

        $this->assertTrue($manifest->isDev(), 'dev manifest should win over prod when both exist');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Production manifest detection — Vite 5 and Vite 4
    // ─────────────────────────────────────────────────────────────────────────

    public function test_loads_vite5_prod_manifest(): void
    {
        $dir = $this->makeProdBuildDir();

        $manifest = Manifest::load($dir);

        $this->assertFalse($manifest->isDev());
        $this->assertObjectHasProperty('src/main.ts', $manifest->getData());
    }

    public function test_loads_vite4_prod_manifest_fallback(): void
    {
        $entries = ['src/app.js' => ['file' => 'assets/app-XYZ.js', 'isEntry' => true]];
        $dir = $this->makeBuildDir($entries, 'manifest.json');  // Vite 4 location

        $manifest = Manifest::load($dir);

        $this->assertFalse($manifest->isDev());
        $this->assertObjectHasProperty('src/app.js', $manifest->getData());
    }

    public function test_vite5_preferred_over_vite4(): void
    {
        // Write Vite 4 manifest first.
        $dir = $this->makeBuildDir(['src/old.js' => ['file' => 'old.js']], 'manifest.json');

        // Then write the Vite 5 manifest.
        $vite5Dir = $dir . '/.vite';
        mkdir($vite5Dir, 0755, true);
        $this->tempDirs[] = $vite5Dir;
        $vite5Path = $vite5Dir . '/manifest.json';
        file_put_contents($vite5Path, json_encode(['src/new.js' => ['file' => 'new.js']]));
        $this->tempFiles[] = $vite5Path;

        $manifest = Manifest::load($dir);

        $this->assertObjectHasProperty('src/new.js', $manifest->getData(), 'Vite 5 manifest should be preferred');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Caching
    // ─────────────────────────────────────────────────────────────────────────

    public function test_returns_same_instance_for_same_dir(): void
    {
        $dir = $this->makeProdBuildDir();

        $a = Manifest::load($dir);
        $b = Manifest::load($dir);

        $this->assertSame($a, $b, 'Manifest::load() should return the cached instance');
    }

    public function test_clearCache_invalidates_entry(): void
    {
        $dir = $this->makeProdBuildDir();

        $a = Manifest::load($dir);
        Manifest::clearCache($dir);
        $b = Manifest::load($dir);

        $this->assertNotSame($a, $b, 'clearCache() should force a fresh load');
    }

    public function test_clearCache_all_invalidates_everything(): void
    {
        $dir1 = $this->makeProdBuildDir();
        $dir2 = $this->makeDevBuildDir();

        $a1 = Manifest::load($dir1);
        $a2 = Manifest::load($dir2);

        Manifest::clearCache();

        $b1 = Manifest::load($dir1);
        $b2 = Manifest::load($dir2);

        $this->assertNotSame($a1, $b1);
        $this->assertNotSame($a2, $b2);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // getDir()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_getDir_returns_normalized_path(): void
    {
        $dir = $this->makeProdBuildDir();

        $manifest = Manifest::load($dir);

        $this->assertSame(
            rtrim(str_replace('\\', '/', $dir), '/'),
            $manifest->getDir(),
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // wp_vite_manifest_data filter
    // ─────────────────────────────────────────────────────────────────────────

    public function test_manifest_data_filter_is_applied(): void
    {
        $dir = $this->makeProdBuildDir();

        $called = false;

        add_filter(
            'wp_vite_manifest_data',
            function (object $data, string $d, string $path, bool $isDev) use ($dir, &$called): object {
                $called = true;
                $this->assertSame($dir, $d);
                $this->assertFalse($isDev);

                return $data;
            },
            10,
            4,
        );

        Manifest::load($dir);

        $this->assertTrue($called, 'wp_vite_manifest_data filter should be called');
    }

    public function test_manifest_data_filter_can_mutate_data(): void
    {
        $dir = $this->makeProdBuildDir();

        add_filter('wp_vite_manifest_data', function (object $data): object {
            $data->injected = true;
            return $data;
        });

        $manifest = Manifest::load($dir);

        $this->assertTrue($manifest->getData()->injected ?? false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Error cases
    // ─────────────────────────────────────────────────────────────────────────

    public function test_throws_when_no_manifest_found(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/No readable manifest/');

        Manifest::load('/tmp/wp-vite-nonexistent-' . uniqid());
    }

    public function test_throws_when_manifest_is_invalid_json(): void
    {
        $dir = sys_get_temp_dir() . '/wp-vite-bad-' . uniqid();
        mkdir($dir, 0755, true);
        $this->tempDirs[] = $dir;

        $path = $dir . '/manifest.json';
        file_put_contents($path, '{ this is not valid json }');
        $this->tempFiles[] = $path;

        $this->expectException(\Throwable::class);

        Manifest::load($dir);
    }
}
