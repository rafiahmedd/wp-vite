# Changelog

All notable changes to **WP Vite** are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

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

[Unreleased]: https://github.com/yourvendor/wp-vite/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/yourvendor/wp-vite/releases/tag/v1.0.0
