# Contributing to WP Vite

Thank you for considering a contribution! Here's everything you need to get up and running.

---

## Table of Contents

- [Requirements](#requirements)
- [Getting Started](#getting-started)
- [Running Tests](#running-tests)
- [Coding Standards](#coding-standards)
- [Submitting a Pull Request](#submitting-a-pull-request)
- [Reporting Bugs](#reporting-bugs)
- [Requesting Features](#requesting-features)

---

## Requirements

| Tool | Version |
|------|---------|
| PHP | ≥ 8.0 |
| Composer | ≥ 2.0 |
| Node.js | ≥ 18 |
| npm | ≥ 9 |

---

## Getting Started

```bash
# 1. Fork the repo, then clone your fork
git clone https://github.com/YOUR_USERNAME/wp-vite.git
cd wp-vite

# 2. Install all dependencies in one step
make install

# Or separately:
composer install
npm ci
```

---

## Running Tests

```bash
# Run the full PHPUnit suite (fast, no coverage)
make test
# or: composer test

# Run with HTML code coverage report (requires Xdebug)
make test-coverage
# or: composer test:coverage

# Check JS plugin syntax
make js-check
# or: npm run test:syntax
```

Tests live in `tests/Unit/`. Each class has a matching test class. The suite uses the stubs in `tests/Stubs/wordpress.php` so **no WordPress installation is required**.

### Writing a new test

1. Add your test class to `tests/Unit/YourClassTest.php`.
2. Extend `WpVite\Tests\WpViteTestCase` — it resets all shared state between each test automatically.
3. Use the fixture helpers (`makeBuildDir`, `makeProdBuildDir`, `makeDevBuildDir`) to create temporary manifest files.

```php
class MyNewTest extends WpViteTestCase
{
    public function test_something(): void
    {
        $dir = $this->makeProdBuildDir();

        $assets = AssetManager::register($dir, 'src/main.ts', ['handle' => 'my-handle']);

        $this->assertContains('my-handle', $assets['scripts']);
    }
}
```

---

## Coding Standards

### PHP

This project follows the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) with PSR-4 namespacing.

```bash
# Check for issues
make lint
# or: composer lint

# Auto-fix what can be fixed
make fix
# or: composer fix
```

Key rules:
- All PHP files must start with `declare(strict_types=1);`.
- Use `readonly` properties (PHP 8.1+) in value objects.
- Match WordPress function signatures exactly in stub files.
- Every public method must have a docblock unless the types make it completely self-evident.

### JavaScript

- The Vite plugin (`js/src/index.js`) is plain ES module JavaScript — no build step, no bundler.
- Use JSDoc for type annotations. TypeScript declarations live separately in `types/index.d.ts`.
- Keep the JS plugin a single file with no npm runtime dependencies.

---

## Submitting a Pull Request

1. **Create a branch** from `main`:
   ```bash
   git checkout -b feat/my-feature
   # or
   git checkout -b fix/issue-42
   ```

2. **Make your changes.** For features, add or update tests. For bug fixes, add a regression test.

3. **Run the full check** before pushing:
   ```bash
   make check
   ```

4. **Write a good commit message:**
   ```
   fix(DevServer): prevent duplicate React preamble injection on multisite

   Previously, when multiple AssetManager::enqueue() calls were made on
   the same request, the preamble could be injected more than once.
   Added a static flag to guard against this.

   Fixes #42
   ```

5. **Open a PR** against `main`. Describe what changed and why. Reference any related issues.

CI will run automatically. All checks must pass before a PR can be merged.

---

## Reporting Bugs

Please [open an issue](https://github.com/yourvendor/wp-vite/issues/new?template=bug_report.md) and include:

- PHP version
- WordPress version
- Vite version
- Minimal reproduction steps
- What you expected vs what happened

---

## Requesting Features

[Open a feature request](https://github.com/yourvendor/wp-vite/issues/new?template=feature_request.md). Describe the use case you're trying to solve, not just the implementation you have in mind. This makes it easier to discuss the best approach.

---

## Project Structure

```
wp-vite/
├── .github/
│   └── workflows/
│       └── ci.yml            CI pipeline (PHP 8.0-8.3, Node 18-22)
├── example/
│   ├── plugin-usage.php      Example PHP usage
│   └── vite.config.js        Example Vite configs (5 variants)
├── js/
│   └── src/
│       └── index.js          Vite plugin (wpVite + wpScripts)
├── src/                      PSR-4: WpVite\
│   ├── AssetManager.php      Main register/enqueue logic
│   ├── AssetOptions.php      Typed value object for options
│   ├── DevServer.php         HMR client + React Refresh preamble
│   ├── Manifest.php          Manifest reader + cache
│   ├── ScriptModuleFilter.php type="module" tag filter
│   ├── UrlResolver.php       Filesystem path → public URL
│   └── functions.php         Procedural helpers
├── tests/
│   ├── Stubs/
│   │   └── wordpress.php     WP function stubs (no WP needed)
│   ├── Unit/                 One test class per src/ class
│   ├── WpViteTestCase.php    Base test case with fixtures
│   └── bootstrap.php         PHPUnit bootstrap
├── types/
│   └── index.d.ts            TypeScript declarations for the JS plugin
├── .editorconfig
├── .gitignore
├── CHANGELOG.md
├── CONTRIBUTING.md
├── LICENSE
├── Makefile
├── README.md
├── composer.json
├── package.json
├── phpcs.xml.dist
└── phpunit.xml.dist
```
