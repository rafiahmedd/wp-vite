<?php

/**
 * Base test case for WP Vite unit tests.
 *
 * @package WpVite\Tests
 */

declare(strict_types=1);

namespace WpVite\Tests;

use PHPUnit\Framework\TestCase;
use WpVite\DevServer;
use WpVite\Manifest;
use WpVite\ScriptModuleFilter;

/**
 * Resets all shared state between each test so tests are fully isolated.
 */
abstract class WpViteTestCase extends TestCase
{
    /**
     * Temporary files created during a test, to be cleaned up in tearDown.
     *
     * @var string[]
     */
    protected array $tempFiles = [];

    /**
     * Temporary directories created during a test.
     *
     * @var string[]
     */
    protected array $tempDirs = [];

    protected function setUp(): void
    {
        parent::setUp();
        _wp_reset_stubs();
        Manifest::clearCache();
        DevServer::reset();
        ScriptModuleFilter::reset();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        foreach (array_reverse($this->tempDirs) as $dir) {
            if (is_dir($dir)) {
                @rmdir($dir);
            }
        }

        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Fixtures
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create a temporary build directory with a JSON manifest file.
     *
     * @param array  $manifest The data to encode as the manifest.
     * @param string $filename Manifest filename relative to build dir.
     *
     * @return string Absolute path to the build directory.
     */
    protected function makeBuildDir(array $manifest, string $filename = 'manifest.json'): string
    {
        $dir = sys_get_temp_dir() . '/wp-vite-test-' . uniqid();
        mkdir($dir, 0755, true);
        $this->tempDirs[] = $dir;

        // Handle nested paths like .vite/manifest.json
        $filePath = $dir . '/' . $filename;
        $fileDir  = dirname($filePath);

        if (! is_dir($fileDir)) {
            mkdir($fileDir, 0755, true);
            $this->tempDirs[] = $fileDir;
        }

        file_put_contents($filePath, json_encode($manifest));
        $this->tempFiles[] = $filePath;

        return $dir;
    }

    /**
     * Create a dev server manifest.
     */
    protected function makeDevBuildDir(array $devManifest = []): string
    {
        $defaults = [
            'origin'  => 'http://localhost:5173',
            'base'    => '/',
            'plugins' => ['wp-vite'],
        ];

        return $this->makeBuildDir(
            array_merge($defaults, $devManifest),
            'vite-dev-server.json',
        );
    }

    /**
     * Create a production manifest (Vite 5 format).
     */
    protected function makeProdBuildDir(array $entries = []): string
    {
        $defaults = [
            'src/main.ts' => [
                'file'    => 'assets/main-Abc123.js',
                'src'     => 'src/main.ts',
                'isEntry' => true,
                'css'     => ['assets/main-Def456.css'],
            ],
        ];

        return $this->makeBuildDir(
            array_merge($defaults, $entries),
            '.vite/manifest.json',
        );
    }
}
