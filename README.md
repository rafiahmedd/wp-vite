# WP Vite

> Vite integration for WordPress plugin and theme development.  
> Dev/prod aware · React HMR · Vite 4 & 5 · PSR-4 · Zero config needed.

---

## Requirements

| Tool | Version |
|------|---------|
| PHP | ≥ 8.0 |
| WordPress | ≥ 5.8 (6.2+ for HTML Tag Processor; falls back gracefully) |
| Vite | ≥ 4.0 |
| Node | ≥ 18 |

---

## Installation

### PHP (Composer)

```bash
composer require rafiahmedd/wp-vite
```

### JS (npm / pnpm / yarn)

```bash
npm install -D @rafiahmedd/wp-vite
```

---

## Quick Start

### 1 — Configure Vite

Create `vite.config.js` in your plugin/theme root:

```js
import { wpVite } from '@rafiahmedd/wp-vite';

export default {
    plugins: [
        wpVite({
            input:  'src/main.ts',  // entry point
            outDir: 'dist',         // output directory
        }),
    ],
};
```

Add scripts to `package.json`:

```json
{
    "scripts": {
        "dev":   "vite",
        "build": "vite build"
    }
}
```

### 2 — Enqueue in PHP

```php
<?php

use WpVite\AssetManager;

require_once __DIR__ . '/vendor/autoload.php';

add_action( 'wp_enqueue_scripts', function (): void {
    AssetManager::enqueue(
        __DIR__ . '/dist',
        'src/main.ts',
        [
            'handle'    => 'my-plugin',
            'in-footer' => true,
        ]
    );
} );
```

### 3 — Develop and build

```bash
npm run dev    # start HMR dev server
npm run build  # build for production
```

That's it. The PHP side automatically detects whether you're in dev or prod mode.

---

## How It Works

### Development mode

When `npm run dev` is running, the Vite plugin writes a `dist/vite-dev-server.json` file:

```json
{
    "origin":  "http://localhost:5173",
    "base":    "/",
    "plugins": ["vite:react-refresh", "wp-vite"]
}
```

The PHP package finds this file and serves assets directly from the Vite dev server — giving you instant HMR.

When the dev server stops, the file is automatically removed.

### Production mode

After `npm run build`, Vite writes either:

- **Vite 5+**: `dist/.vite/manifest.json`
- **Vite 4**: `dist/manifest.json`

The PHP package detects whichever format is present and uses it to resolve hashed filenames for scripts and styles.

---

## PHP API

### `AssetManager::enqueue( $buildDir, $entry, $options )`

Register and enqueue a Vite entry point.

```php
use WpVite\AssetManager;

AssetManager::enqueue(
    __DIR__ . '/dist',
    'src/main.tsx',
    [
        'handle'           => 'my-plugin',           // required
        'dependencies'     => [ 'wp-element' ],      // script deps
        'css-dependencies' => [ 'wp-components' ],   // style deps
        'css-media'        => 'all',                 // CSS media attribute
        'css-only'         => false,                 // true = no JS tag
        'in-footer'        => true,                  // load in footer
    ]
);
```

### `AssetManager::register( $buildDir, $entry, $options )`

Register only — returns handles you can enqueue yourself later.

```php
$assets = AssetManager::register( __DIR__ . '/dist', 'src/main.ts', [
    'handle' => 'my-plugin',
] );

// $assets = [ 'scripts' => [ 'my-plugin' ], 'styles' => [ 'my-plugin-main' ] ]

wp_enqueue_script( 'my-plugin' );
wp_enqueue_style( 'my-plugin-main' );
```

### Procedural helpers (opt-in)

`wp_vite_enqueue()` / `wp_vite_register()` are thin global wrappers. They are
**not autoloaded** — see [Using this package in more than one
plugin](#using-this-package-in-more-than-one-plugin) for why. Load them once from
your bootstrap:

```php
require_once __DIR__ . '/vendor/autoload.php';

\WpVite\AssetManager::helpers();

wp_vite_enqueue( __DIR__ . '/dist', 'src/main.tsx', [ 'handle' => 'my-plugin' ] );
AssetManager::register( __DIR__ . '/dist', 'src/admin.ts', [ 'handle' => 'my-admin' ] );
```

---

## React Support

Install the React plugin:

```bash
npm install -D @vitejs/plugin-react
npm install react react-dom
```

Update `vite.config.js`:

```js
import { wpVite } from '@rafiahmedd/wp-vite';
import react from '@vitejs/plugin-react';

export default {
    plugins: [
        wpVite({ input: 'src/main.jsx', outDir: 'dist' }),
        react(),
    ],
};
```

React Fast Refresh (HMR) is injected automatically during development.

---

## Externalising WordPress Packages (smaller bundles)

WordPress ships React, jQuery, and all `@wordpress/*` packages as global scripts. You can tell Vite to exclude them from your bundle using the `wpScripts()` plugin:

```js
import { wpVite, wpScripts } from '@rafiahmedd/wp-vite';
import react from '@vitejs/plugin-react';

export default {
    plugins: [
        wpVite({ input: 'src/main.jsx', outDir: 'dist' }),
        wpScripts(),   // exclude react, react-dom, @wordpress/* from bundle
        react(),
    ],
};
```

Then declare the handles as dependencies on the PHP side:

```php
AssetManager::enqueue( __DIR__ . '/dist', 'src/main.jsx', [
    'handle'       => 'my-plugin',
    'dependencies' => [ 'react', 'react-dom', 'wp-element' ],
] );
```

### Customising the externals map

```js
// Add extra globals
wpScripts({ lodash: '_', 'my-lib': 'MyLib' });

// Remove an entry from the defaults (include react in your bundle)
wpScripts({ react: false, 'react-dom': false });
```

Default externals map:

| Package | Global |
|---------|--------|
| `jquery` | `jQuery` |
| `react` | `React` |
| `react-dom` | `ReactDOM` |
| `react-dom/client` | `ReactDOM` |
| `@wordpress/api-fetch` | `wp.apiFetch` |
| `@wordpress/blocks` | `wp.blocks` |
| `@wordpress/block-editor` | `wp.blockEditor` |
| `@wordpress/components` | `wp.components` |
| `@wordpress/compose` | `wp.compose` |
| `@wordpress/data` | `wp.data` |
| `@wordpress/element` | `wp.element` |
| `@wordpress/hooks` | `wp.hooks` |
| `@wordpress/i18n` | `wp.i18n` |
| `@wordpress/notices` | `wp.notices` |
| `@wordpress/url` | `wp.url` |

---

## Multiple Entry Points

Each entry must be enqueued separately:

```js
// vite.config.js
wpVite({
    input: {
        main:  'src/main.ts',
        admin: 'src/admin.ts',
    },
    outDir: 'dist',
});
```

```php
// plugin.php
AssetManager::enqueue( __DIR__ . '/dist', 'src/main.ts',  [ 'handle' => 'my-plugin-frontend' ] );
AssetManager::enqueue( __DIR__ . '/dist', 'src/admin.ts', [ 'handle' => 'my-plugin-admin' ] );
```

---

## Using this package in more than one plugin

WordPress runs every plugin in one PHP process, but Composer resolves
dependencies per project — so two plugins can each vendor their own copy of this
package. Three globals are shared across all of them:

**1. `autoload.files` — why the helpers are opt-in.** Composer keys each `files`
entry by `md5( "<package-name>:<relative-path>" )` and records what it has loaded
in `$GLOBALS['__composer_autoload_files']`, which every autoloader in the process
shares. Two plugins vendoring this package produce the *same* key: the first to
boot requires its copy, and every later plugin's autoloader **skips its own copy
entirely** — a fatal `Call to undefined function wp_vite_enqueue()`. Prefixing the
vendored copy does not help, because the key comes from the package name, not the
contents. Nothing inside a shared package can fix that, so this package ships no
`autoload.files` at all: call `AssetManager::helpers()` if you want the globals,
or just use `AssetManager::enqueue()` and never think about it.

**2. The WP script registry.** The HMR client handle is derived from the dev
server's origin, not hardcoded, so two plugins running separate dev servers no
longer overwrite each other's `vite-client`. Your *own* handles are still yours to
keep unique — prefix them with your plugin slug.

**3. The `WpVite\` namespace and the `wp_vite_*` filters.** Whichever autoloader
registers first owns the namespace for the request, so a site running two plugins
with different versions of this package silently runs one of them against the
other's code. If you ship to wordpress.org, prefix your vendored copy
(php-scoper, or an equivalent build step).

---

## Available Filters (WordPress)

| Filter | Description |
|--------|-------------|
| `wp_vite_manifest_data` | Modify raw manifest data after it's read from disk. |
| `wp_vite_registered_assets` | Alter the array of registered handles after any mode. |
| `wp_vite_dev_assets` | Alter handles registered in development mode. |
| `wp_vite_prod_assets` | Alter handles registered in production mode. |

Example — inject `window.MyPlugin` before the script runs:

```php
add_filter( 'wp_vite_registered_assets', function ( array $assets ): array {
    if ( in_array( 'my-plugin', $assets['scripts'], true ) ) {
        wp_add_inline_script(
            'my-plugin',
            'window.MyPlugin = ' . wp_json_encode( [ 'ajax' => admin_url( 'admin-ajax.php' ) ] ) . ';',
            'before'
        );
    }

    return $assets;
} );
```

---

## Directory Structure

### This package

```
wp-vite/
├── .github/workflows/ci.yml     CI — PHP 8.0–8.3, Node 18–22
├── example/
│   ├── plugin-usage.php         PHP usage examples (5 patterns)
│   └── vite.config.js           Vite config examples (5 variants)
├── js/src/index.js              Vite plugin — wpVite() + wpScripts()
├── src/                         PSR-4 namespace: WpVite\
│   ├── AssetManager.php         register() / enqueue() — core logic
│   ├── AssetOptions.php         Typed readonly value object for options
│   ├── DevServer.php            @vite/client + React Refresh preamble
│   ├── Manifest.php             Manifest reader, cache, Vite 4+5 support
│   ├── ScriptModuleFilter.php   type="module" injection (WP 6.2+ + fallback)
│   ├── UrlResolver.php          Filesystem path → public WordPress URL
│   └── functions.php            wp_vite_enqueue() / wp_vite_register() — opt-in
├── tests/
│   ├── Stubs/wordpress.php      WP function stubs — no WP install needed
│   ├── Unit/                    One test class per src/ class (7 files)
│   ├── WpViteTestCase.php       Base case with manifest fixture helpers
│   └── bootstrap.php
├── types/index.d.ts             TypeScript declarations for the JS plugin
├── .editorconfig
├── .gitignore
├── CHANGELOG.md
├── CONTRIBUTING.md
├── LICENSE
├── Makefile                     make test / lint / fix / check / clean
├── README.md
├── SECURITY.md
├── composer.json
├── package.json
├── phpcs.xml.dist
└── phpunit.xml.dist
```

### Your plugin/theme (consuming this package)

```
your-plugin/
├── dist/                        ← build output (git-ignored)
│   ├── .vite/
│   │   └── manifest.json        ← Vite 5 production manifest
│   ├── manifest.json            ← Vite 4 production manifest (fallback)
│   └── vite-dev-server.json     ← written on `npm run dev`, auto-deleted
├── src/
│   └── main.tsx
├── composer.json
├── package.json
├── plugin.php
└── vite.config.js
```

---

## Developer Commands

```bash
make install        # composer install + npm ci
make test           # PHPUnit (no coverage)
make test-coverage  # PHPUnit + HTML coverage report (needs Xdebug)
make lint           # PHPCS check
make fix            # PHPCBF auto-fix
make check          # lint + test (same as CI)
make clean          # remove vendor/, node_modules/, coverage/
make js-check       # verify JS plugin syntax with Node
```

Or via Composer:

```bash
composer test
composer lint
composer fix
composer check
```

---

## License

MIT
