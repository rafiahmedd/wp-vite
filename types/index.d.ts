/**
 * WP Vite — TypeScript declarations
 *
 * Provides full type information for both exports:
 *   import { wpVite, wpScripts } from '@yourvendor/wp-vite';
 */

import type { Plugin } from 'vite';

// ─────────────────────────────────────────────────────────────────────────────
// wpVite
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Entry point definition, matching Rollup's input format.
 *
 * - `string`                    — single file: `'src/main.ts'`
 * - `string[]`                  — multiple files: `['src/main.ts', 'src/admin.ts']`
 * - `Record<string, string>`    — named entries: `{ main: 'src/main.ts', admin: 'src/admin.ts' }`
 */
export type WpViteInput = string | string[] | Record<string, string>;

/**
 * Options for the {@link wpVite} plugin.
 */
export interface WpViteOptions {
    /**
     * Entry point(s) for Vite/Rollup.
     *
     * Accepts any format Rollup supports.
     *
     * @default 'src/main.js'
     *
     * @example
     * // Single entry
     * input: 'src/main.ts'
     *
     * @example
     * // Multiple entries (array)
     * input: ['src/main.ts', 'src/admin.ts']
     *
     * @example
     * // Named entries (object)
     * input: { main: 'src/main.ts', admin: 'src/admin.ts' }
     */
    input?: WpViteInput;

    /**
     * Output directory, relative to the project root.
     *
     * This directory will contain the built assets and the manifest file(s).
     * During development, `vite-dev-server.json` is also written here.
     *
     * @default 'dist'
     */
    outDir?: string;
}

/**
 * Core Vite plugin for WordPress development.
 *
 * - Configures Vite/Rollup input and output to emit `manifest.json`.
 * - Writes `<outDir>/vite-dev-server.json` on `npm run dev` so the PHP package
 *   can connect to the HMR server.
 * - Removes the dev manifest on `npm run build` to prevent stale data.
 *
 * @param options Plugin options.
 * @returns A Vite plugin instance.
 *
 * @example
 * ```js
 * // vite.config.js
 * import { wpVite } from '@yourvendor/wp-vite';
 *
 * export default {
 *     plugins: [
 *         wpVite({ input: 'src/main.ts', outDir: 'dist' }),
 *     ],
 * };
 * ```
 */
export declare function wpVite(options?: WpViteOptions): Plugin;

// ─────────────────────────────────────────────────────────────────────────────
// wpScripts
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Map of npm package names to their global variable name as provided by WordPress.
 *
 * Set a value to `false` to remove that package from the externals map (bundle it yourself).
 *
 * @example
 * // Add lodash as a WordPress global
 * { lodash: '_' }
 *
 * @example
 * // Bundle react yourself instead of using wp.element
 * { react: false, 'react-dom': false }
 */
export type WpScriptsOverrides = Record<string, string | false>;

/**
 * Vite plugin that externalises WordPress-bundled packages.
 *
 * Packages like `react`, `react-dom`, and all `@wordpress/*` packages are
 * already loaded by WordPress as global scripts. Excluding them from your
 * bundle reduces bundle size and avoids version conflicts.
 *
 * @param overrides Additional or overriding entries for the globals map.
 *                  Set a package name to `false` to remove it from the defaults.
 * @returns A Vite plugin instance.
 *
 * @example
 * ```js
 * // vite.config.js
 * import { wpVite, wpScripts } from '@yourvendor/wp-vite';
 * import react from '@vitejs/plugin-react';
 *
 * export default {
 *     plugins: [
 *         wpVite({ input: 'src/main.jsx', outDir: 'dist' }),
 *         wpScripts(),   // externalise all default WP packages
 *         react(),
 *     ],
 * };
 * ```
 *
 * @example
 * ```js
 * // Custom overrides — add lodash, remove react
 * wpScripts({ lodash: '_', react: false, 'react-dom': false });
 * ```
 */
export declare function wpScripts(overrides?: WpScriptsOverrides): Plugin;

// ─────────────────────────────────────────────────────────────────────────────
// Dev manifest shape (informational — written by wpVite to <outDir>/vite-dev-server.json)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * The JSON structure written to `<outDir>/vite-dev-server.json` during `npm run dev`.
 *
 * The PHP package reads this file to connect to the Vite HMR server.
 */
export interface DevManifest {
    /** Full origin URL of the Vite dev server, e.g. `http://localhost:5173`. */
    origin: string;
    /** Vite base path, e.g. `/` or `/my-plugin`. */
    base: string;
    /** Names of all active Vite plugins (used to detect `vite:react-refresh`). */
    plugins: string[];
}
