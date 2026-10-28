# Changelog

All notable changes to **WP Vite** are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [2.0.1] — 2026-10-28

### Fixed
- `UrlResolver::resolve()` builds the right URL for a plugin whose folder is a
  symlink. PHP reports such a plugin's real path, outside `WP_CONTENT_DIR`, so
  the asset URL carried a filesystem path and every script 404'd. The resolver
  now maps it back through WordPress's own symlink map
  (`wp_register_plugin_realpath()` → `plugins_url()`).

---

## [2.0.0] — 2026-09-04

### Fixed

- **Two plugins vendoring this package no longer fatal each other.** `autoload.files`
  is keyed by `md5( "<package-name>:<relative-path>" )` and tracked in the
  process-wide `$GLOBALS['__composer_autoload_files']`, so the first plugin to
  boot loaded `src/functions.php` and every later plugin's autoloader skipped its
  own copy — leaving them with `Call to undefined function wp_vite_enqueue()`.
  Prefixing the vendored copy did not help; the key comes from the package name.
  The package no longer declares `autoload.files` at all.
- **The `@vite/client` script handle is derived from the dev server origin** instead
  of the fixed `vite-client`. Two plugins running their own dev servers each got
  one shot at that global WP registry key, and the loser silently loaded the
  winner's dev server. The React Refresh preamble is likewise tracked per handle.

### Changed — BREAKING

- `wp_vite_enqueue()` / `wp_vite_register()` are opt-in. Either call
  `WpVite\AssetManager::helpers()` once from your plugin bootstrap, or switch to
  `AssetManager::enqueue()` / `AssetManager::register()` (recommended — PSR-4, and
  never subject to any of this).
- `DevServer::registerClient()` now returns the handle it registered.
- `DevServer::CLIENT_HANDLE` is the handle *prefix*, not the handle. Use
  `DevServer::clientHandle( $manifest )`.

---

## [1.0.0] — 2026-04-01

### Added

**PHP package**

- `AssetManager::register()` — register a Vite entry point and return script/style handles.
- `AssetManager::enqueue()` — register + enqueue in one call.
- `wp_vite_register()` / `wp_vite_enqueue()` — procedural helper functions for drop-in simplicity.
- `Manifest` — reads and caches the Vite manifest. Auto-detects:
  - Dev mode: `<outDir>/vite-dev-server.json`
  - Production Vite 5+: `<outDir>/.vite/manifest.json`
  - Production Vite 4: `<outDir>/manifest.json`
- `DevServer` — registers `@vite/client` and injects the React Refresh preamble when `vite:react-refresh` is active.
- `ScriptModuleFilter` — adds `type="module"` to script tags. Uses `WP_HTML_Tag_Processor` (WP 6.2+) with a regex fallback for older WordPress versions.
- `UrlResolver` — converts filesystem paths to public URLs, supporting subdirectory WP installs.
- `AssetOptions` — typed, readonly value object for all enqueue options.
- WordPress filters: `wp_vite_manifest_data`, `wp_vite_registered_assets`, `wp_vite_dev_assets`, `wp_vite_prod_assets`.
- Full PHPUnit test suite with WordPress stubs (no WP install required to run tests).

**JavaScript / Vite plugin**

- `wpVite(options)` — core Vite plugin. Configures input/output, writes `vite-dev-server.json` on dev start, removes it on build or server close.
- `wpScripts(overrides?)` — externalises WordPress-bundled packages (`react`, `react-dom`, `@wordpress/*`, `jquery`) from your bundle, with a configurable overrides map.
- React Fast Refresh supported out of the box — just add `@vitejs/plugin-react`.
- Compatible with **Vite 4** and **Vite 5**.
- Full TypeScript declarations (`types/index.d.ts`).

**Tooling**

- GitHub Actions CI matrix: PHP 8.0 – 8.3, Node 18 – 22.
- PHPCS config (`phpcs.xml.dist`) enforcing WordPress coding standards.
- `.editorconfig` for consistent formatting.

[Unreleased]: https://github.com/rafiahmedd/wp-vite/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/rafiahmedd/wp-vite/compare/v1.0.5...v2.0.0
[1.0.0]: https://github.com/rafiahmedd/wp-vite/releases/tag/v1.0.0
