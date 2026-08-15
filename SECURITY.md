# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.x     | ✅ Yes    |

Older versions do not receive security fixes. Please upgrade to the latest `1.x` release.

---

## Reporting a Vulnerability

**Please do not open a public GitHub issue for security vulnerabilities.**

Instead, report them privately:

1. Go to the **Security** tab of this repository.
2. Click **"Report a vulnerability"**.
3. Fill in the details — what the issue is, how to reproduce it, and its potential impact.

You can also email: **security@yourvendor.com**

We will:
- Acknowledge your report within **48 hours**.
- Keep you informed as we investigate and patch the issue.
- Credit you in the release notes if you wish (or keep it anonymous — your choice).

---

## Scope

This package is a library that manages how WordPress enqueues Vite-built JavaScript and CSS assets. Security considerations include:

- **Path traversal** — the manifest reader and URL resolver accept filesystem paths. Please report any case where these could be manipulated to read files outside the intended build directory.
- **XSS via manifest data** — all manifest-sourced values that are written to the page (script src, inline script content) should be properly escaped.
- **Dev manifest left in production** — the `vite-dev-server.json` file points to a local dev server. If it were accidentally committed and deployed it could load scripts from an attacker-controlled server. The `.gitignore` guards against this; please report any bypass.

---

## Out of Scope

- Issues in third-party dependencies (Vite, WordPress core, `@vitejs/plugin-react`). Please report those to their respective maintainers.
- Issues in code you write using this package (e.g. unsafe use of the `wp_vite_manifest_data` filter to inject arbitrary HTML).
