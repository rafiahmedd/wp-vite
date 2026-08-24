/**
 * WP Vite — Vite plugin for WordPress development
 *
 * Provides two named exports:
 *
 *   wpVite(options)    — Core plugin. Configures input/output and writes
 *                        vite-dev-server.json during `npm run dev`.
 *
 *   wpScripts(map?)   — Optional plugin. Externalises WordPress-bundled
 *                        packages (react, react-dom, @wordpress/*) so they
 *                        are not included in your bundle.
 */

import { existsSync, mkdirSync, readFileSync, unlinkSync, writeFileSync } from 'node:fs';
import { createConnection } from 'node:net';
import { resolve, join } from 'node:path';

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const DEV_MANIFEST_FILE = 'vite-dev-server.json';

// ─────────────────────────────────────────────────────────────────────────────
// wpVite — core plugin
// ─────────────────────────────────────────────────────────────────────────────

/**
 * @typedef {Object} WpViteOptions
 * @property {string | string[] | Record<string, string>} [input='src/main.js']
 *   Entry point(s). Accepts any format Rollup supports.
 * @property {string} [outDir='dist']
 *   Output directory, relative to the project root.
 */

/**
 * Core Vite plugin for WordPress development.
 *
 * What it does:
 *   - Configures Rollup input / output so Vite writes a manifest.json.
 *   - On `npm run dev`: writes `<outDir>/vite-dev-server.json` so the PHP
 *     package can connect to the HMR server.
 *   - On `npm run build`: removes the dev manifest so the PHP package falls
 *     through to the production manifest.
 *
 * @param {WpViteOptions} [options]
 * @returns {import('vite').Plugin}
 */
export function wpVite(options = {}) {
    const { input = 'src/main.js', outDir = 'dist' } = options;

    /** @type {string} Resolved absolute output directory */
    let resolvedOutDir;

    return {
        name: 'wp-vite',

        // ── Vite config hook ─────────────────────────────────────────────────
        config(userConfig, { command }) {
            const root = userConfig.root ?? process.cwd();
            resolvedOutDir = resolve(root, outDir);

            return {
                build: {
                    // Emit manifest.json (Vite 5: .vite/manifest.json)
                    manifest: true,
                    outDir: resolvedOutDir,
                    rollupOptions: {
                        input: normalizeInput(input, root),
                    },
                },
            };
        },

        // ── Dev server ───────────────────────────────────────────────────────
        configureServer(server) {
            // Write the dev manifest once the HTTP server is listening.
            server.httpServer?.once('listening', () => {
                const address = server.httpServer.address();
                const isHttps = !!server.config.server?.https;
                const protocol = isHttps ? 'https' : 'http';

                let host = 'localhost';
                let port = 5173;

                if (address && typeof address === 'object') {
                    port = address.port;
                    // Vite may bind to 0.0.0.0; use configured host or localhost.
                    const configuredHost = server.config.server?.host;
                    if (configuredHost && configuredHost !== '0.0.0.0' && configuredHost !== true) {
                        host = String(configuredHost);
                    }
                }

                const origin = `${protocol}://${host}:${port}`;

                /** @type {string[]} */
                const pluginNames = server.config.plugins
                    .flat()
                    .map((p) => p?.name)
                    .filter(Boolean);

                const devManifest = {
                    origin,
                    base: server.config.base ?? '/',
                    plugins: pluginNames,
                };

                ensureDir(resolvedOutDir);

                writeFileSync(
                    join(resolvedOutDir, DEV_MANIFEST_FILE),
                    JSON.stringify(devManifest, null, 2),
                    'utf-8',
                );

                server.config.logger.info(
                    `  \x1b[32m➜\x1b[0m  WP Vite: dev manifest written to ${outDir}/${DEV_MANIFEST_FILE}`,
                );
            });

            // Clean up the dev manifest when the server closes so the PHP side
            // does not accidentally use it after a build.
            server.httpServer?.once('close', () => {
                removeFile(join(resolvedOutDir, DEV_MANIFEST_FILE));
            });
        },

        // ── Build start ──────────────────────────────────────────────────────
        async buildStart() {
            const devManifestPath = join(resolvedOutDir, DEV_MANIFEST_FILE);

            /*
             * Remove a LEFTOVER dev manifest, never a live one.
             *
             * Deleting unconditionally meant `npm run build` in a second
             * terminal silently switched a running `npm run dev` session back
             * to the built bundle: the dev server keeps serving on its port,
             * but the file PHP reads to find that port is gone, so
             * Manifest::load() falls through to the production manifest and
             * every entry is served from dist. Nothing recreates the file —
             * it is only written on 'listening' — so the session stays broken
             * until the dev server is restarted.
             *
             * Probing the recorded origin distinguishes the two cases the one
             * file cannot: unreachable means the writer is gone (crash, kill
             * -9, close handler that never ran) and the stale file must go,
             * because it would otherwise point a production site at a dead
             * localhost port. Reachable means a dev server still owns it.
             *
             * Safe in dev too: this runs before our own server listens, so
             * this build can never mistake itself for the live one.
             */
            if (await isOriginReachable(devManifestPath)) {
                return;
            }

            removeFile(devManifestPath);
        },
    };
}

// ─────────────────────────────────────────────────────────────────────────────
// wpScripts — WordPress externals plugin
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Default map of npm package names → global variable names provided by WordPress.
 *
 * These packages are already bundled and registered by WordPress core, so
 * excluding them from your build reduces bundle size and avoids conflicts.
 *
 * @type {Record<string, string>}
 */
const WP_GLOBAL_MAP = {
    jquery: 'jQuery',
    react: 'React',
    'react-dom': 'ReactDOM',
    'react-dom/client': 'ReactDOM',
    '@wordpress/api-fetch': 'wp.apiFetch',
    '@wordpress/blocks': 'wp.blocks',
    '@wordpress/block-editor': 'wp.blockEditor',
    '@wordpress/components': 'wp.components',
    '@wordpress/compose': 'wp.compose',
    '@wordpress/data': 'wp.data',
    '@wordpress/dom-ready': 'wp.domReady',
    '@wordpress/element': 'wp.element',
    '@wordpress/hooks': 'wp.hooks',
    '@wordpress/html-entities': 'wp.htmlEntities',
    '@wordpress/i18n': 'wp.i18n',
    '@wordpress/notices': 'wp.notices',
    '@wordpress/url': 'wp.url',
};

/**
 * Vite plugin that externalises WordPress-bundled packages.
 *
 * Usage:
 *   wpScripts()                 — Use the built-in WP global map.
 *   wpScripts({ lodash: '_' })  — Merge custom globals into the map.
 *   wpScripts({ react: false }) — Remove react from the map (bundle it yourself).
 *
 * @param {Record<string, string | false>} [overrides]
 *   Packages to add or remove (set value to `false` to exclude a default entry).
 *
 * @returns {import('vite').Plugin}
 */
export function wpScripts(overrides = {}) {
    // Build the final externals map, allowing the caller to remove entries.
    const globals = Object.fromEntries(
        Object.entries({ ...WP_GLOBAL_MAP, ...overrides }).filter(
            ([, value]) => value !== false,
        ),
    );

    const external = Object.keys(globals);

    // Pre-build regex for each external package (used in dev transform).
    const escapedEntries = Object.entries(globals).map(([pkg, globalName]) => ({
        pkg,
        globalName,
        escaped: pkg.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'),
    }));

    return {
        name: 'wp-scripts-externals',
        // Must run before vite:import-analysis so the transform rewrites
        // imports before Vite tries to resolve them.
        enforce: 'pre',

        config(_, { command }) {
            // Rollup externals only apply during build.
            if (command === 'build') {
                return {
                    build: {
                        rollupOptions: {
                            external,
                            output: {
                                globals,
                            },
                        },
                    },
                };
            }
        },

        // Dev mode: rewrite imports to global variable access before
        // vite:import-analysis runs, so Vite never tries to resolve them.
        transform(code, id) {
            // Skip virtual modules and non-JS files.
            if (id.startsWith('\0')) return;
            if (!/\.(js|jsx|ts|tsx|mjs|cjs)(\?.*)?$/.test(id)) return;

            let result = code;
            let changed = false;

            for (const { pkg, globalName, escaped } of escapedEntries) {
                // Skip packages not referenced in this file (fast bail-out).
                if (!result.includes(pkg)) continue;

                const q = `['"]${escaped}['"]`; // matches 'pkg' or "pkg"

                // import { a, b as c } from 'pkg'  →  const { a, b: c } = window.wp.xxx
                result = result.replace(
                    new RegExp(`import\\s*\\{([^}]+)\\}\\s*from\\s*${q}\\s*;?`, 'g'),
                    (_, names) => {
                        changed = true;
                        // Rewrite " as " → ": " for destructuring syntax.
                        const destructured = names.replace(/\bas\b/g, ':');
                        return `const {${destructured}} = window.${globalName};`;
                    },
                );

                // import * as ns from 'pkg'  →  const ns = window.wp.xxx
                result = result.replace(
                    new RegExp(`import\\s*\\*\\s*as\\s+(\\w+)\\s+from\\s*${q}\\s*;?`, 'g'),
                    (_, name) => { changed = true; return `const ${name} = window.${globalName};`; },
                );

                // import def, { a } from 'pkg'  →  const def = window.wp.xxx; const { a } = def;
                result = result.replace(
                    new RegExp(`import\\s+(\\w+)\\s*,\\s*\\{([^}]+)\\}\\s*from\\s*${q}\\s*;?`, 'g'),
                    (_, def, names) => {
                        changed = true;
                        const destructured = names.replace(/\bas\b/g, ':');
                        return `const ${def} = window.${globalName}; const {${destructured}} = ${def};`;
                    },
                );

                // import def from 'pkg'  →  const def = window.wp.xxx
                result = result.replace(
                    new RegExp(`import\\s+(\\w+)\\s+from\\s*${q}\\s*;?`, 'g'),
                    (_, name) => { changed = true; return `const ${name} = window.${globalName};`; },
                );

                // import 'pkg'  (side-effect only) → remove
                result = result.replace(
                    new RegExp(`import\\s*${q}\\s*;?`, 'g'),
                    () => { changed = true; return `/* ${pkg} (external) */`; },
                );
            }

            if (changed) {
                return { code: result, map: null };
            }
        },
    };
}

// ─────────────────────────────────────────────────────────────────────────────
// Utilities
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Normalise the `input` option to the format Rollup expects.
 *
 * @param {string | string[] | Record<string, string>} input
 * @param {string} root Project root (used to resolve relative paths).
 * @returns {string[] | Record<string, string>}
 */
function normalizeInput(input, root) {
    if (typeof input === 'string') {
        return [resolve(root, input)];
    }

    if (Array.isArray(input)) {
        return input.map((entry) => resolve(root, entry));
    }

    // Object form — resolve each value.
    return Object.fromEntries(
        Object.entries(input).map(([key, path]) => [key, resolve(root, path)]),
    );
}

/**
 * Create a directory (and all parents) if it does not exist.
 *
 * @param {string} dir
 */
function ensureDir(dir) {
    if (!existsSync(dir)) {
        mkdirSync(dir, { recursive: true });
    }
}

/**
 * Whether the dev server recorded in a dev manifest still accepts connections.
 *
 * Resolves false for anything unusable — missing, unparseable, or originless
 * file, refused connection, or a host that does not answer promptly — so the
 * caller falls through to deleting a manifest it cannot vouch for.
 *
 * @param {string} manifestPath Path to the dev manifest.
 * @returns {Promise<boolean>}
 */
function isOriginReachable(manifestPath) {
    /** @type {string | undefined} */
    let origin;

    try {
        origin = JSON.parse(readFileSync(manifestPath, 'utf-8')).origin;
    } catch {
        return Promise.resolve(false);
    }

    if (!origin) return Promise.resolve(false);

    /** @type {URL} */
    let url;
    try {
        url = new URL(origin);
    } catch {
        return Promise.resolve(false);
    }

    return new Promise((resolveProbe) => {
        // ponytail: a TCP connect, not an HTTP request — we only need to know
        // that something still holds the port. Swap in a fetch of
        // `${origin}/@vite/client` if a non-Vite squatter on the port ever
        // becomes a real problem.
        const socket = createConnection({
            host: url.hostname,
            port: Number(url.port) || (url.protocol === 'https:' ? 443 : 80),
        });

        const settle = (reachable) => {
            socket.destroy();
            resolveProbe(reachable);
        };

        socket.setTimeout(500);
        socket.once('connect', () => settle(true));
        socket.once('error', () => settle(false));
        socket.once('timeout', () => settle(false));
    });
}

/**
 * Silently remove a file (no-op if it does not exist).
 *
 * @param {string} filePath
 */
function removeFile(filePath) {
    try {
        unlinkSync(filePath);
    } catch {
        // Ignore "file not found" and similar errors.
    }
}
