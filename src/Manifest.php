<?php

/**
 * Manifest reader for WP Vite
 *
 * @package WpVite
 */

declare(strict_types=1);

namespace WpVite;

use RuntimeException;

/**
 * Reads and caches the Vite manifest file.
 *
 * Supports:
 *  - Development mode: vite-dev-server.json
 *  - Production Vite 5+: .vite/manifest.json
 *  - Production Vite 4:  manifest.json
 */
class Manifest
{
    /**
     * Dev server manifest filename (written by our Vite plugin during `npm run dev`).
     */
    private const DEV_MANIFEST = 'vite-dev-server.json';

    /**
     * Production manifest path for Vite 5+.
     */
    private const PROD_MANIFEST_V5 = '.vite/manifest.json';

    /**
     * Production manifest path for Vite 4.
     */
    private const PROD_MANIFEST_V4 = 'manifest.json';

    /**
     * In-memory cache of loaded manifests, keyed by normalized build dir.
     *
     * @var array<string, self>
     */
    private static array $cache = [];

    private function __construct(
        private readonly string $dir,
        private readonly object $data,
        private readonly bool $isDev,
    ) {}

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Load (or return a cached) Manifest for the given build directory.
     *
     * @param string $buildDir Absolute path to the build/dist directory.
     *
     * @throws RuntimeException When no readable manifest file is found or parsing fails.
     */
    public static function load(string $buildDir): self
    {
        $dir = wp_normalize_path(untrailingslashit($buildDir));

        if (isset(self::$cache[$dir])) {
            return self::$cache[$dir];
        }

        // Priority: dev > prod v5 > prod v4
        $candidates = [
            self::DEV_MANIFEST     => true,
            self::PROD_MANIFEST_V5 => false,
            self::PROD_MANIFEST_V4 => false,
        ];

        foreach ($candidates as $filename => $isDev) {
            $path = "{$dir}/{$filename}";

            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }

            $data = self::decodeJson($path);

            /**
             * Filters the raw manifest data after it has been read.
             *
             * @param object $data        Decoded manifest object.
             * @param string $dir         Absolute path to the build directory.
             * @param string $path        Full path to the manifest file.
             * @param bool   $isDev       Whether this is a dev-server manifest.
             */
            $data = apply_filters('wp_vite_manifest_data', $data, $dir, $path, $isDev);

            return self::$cache[$dir] = new self($dir, $data, $isDev);
        }

        throw new RuntimeException(
            sprintf(
                '[WP Vite] No readable manifest found in "%s". Did you run `npm run dev` or `npm run build`?',
                $dir,
            )
        );
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    /**
     * The decoded manifest data object.
     */
    public function getData(): object
    {
        return $this->data;
    }

    /**
     * Absolute path to the build directory.
     */
    public function getDir(): string
    {
        return $this->dir;
    }

    /**
     * Whether this is a development (HMR) manifest.
     */
    public function isDev(): bool
    {
        return $this->isDev;
    }

    // -------------------------------------------------------------------------
    // Cache management
    // -------------------------------------------------------------------------

    /**
     * Clear the manifest cache.
     *
     * @param string|null $buildDir Specific directory to clear, or null to clear all.
     */
    public static function clearCache(?string $buildDir = null): void
    {
        if ($buildDir !== null) {
            unset(self::$cache[wp_normalize_path(untrailingslashit($buildDir))]);
            return;
        }

        self::$cache = [];
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Decode a JSON manifest file.
     *
     * @throws RuntimeException On parse failure.
     */
    private static function decodeJson(string $path): object
    {
        $data = wp_json_file_decode($path);

        if (! $data) {
            throw new RuntimeException(
                sprintf('[WP Vite] Failed to parse manifest file: %s', $path)
            );
        }

        return $data;
    }
}
