/**
 * Self-check for wpVite()'s buildStart: a build must delete a STALE dev
 * manifest but keep one whose dev server is still listening.
 *
 * Run: node js/test/dev-manifest-preserved.mjs
 */
import assert from 'node:assert/strict';
import { createServer } from 'node:net';
import { existsSync, mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { wpVite } from '../src/index.js';

const DEV_FILE = 'vite-dev-server.json';
const root = mkdtempSync(join(tmpdir(), 'wp-vite-'));

/** Write a dev manifest naming `origin`, and return its path. */
function seed(origin, body) {
    const dir = join(root, 'dist');
    mkdirSync(dir, { recursive: true });
    const file = join(dir, DEV_FILE);
    writeFileSync(file, body ?? JSON.stringify({ origin, base: '/', plugins: [] }));
    return file;
}

/** Drive the plugin's config + buildStart hooks against the throwaway outDir. */
async function runBuildStart() {
    const plugin = wpVite({ input: 'src/main.js', outDir: 'dist' });
    plugin.config({ root }, { command: 'build' });
    await plugin.buildStart();
}

const server = createServer();
await new Promise((r) => server.listen(0, '127.0.0.1', r));
const origin = `http://127.0.0.1:${server.address().port}`;

// A dev server still holding the port owns the file — a build must not take it.
let file = seed(origin);
await runBuildStart();
assert.equal(existsSync(file), true, 'live dev manifest must survive a build');

// Same origin, nothing listening: the writer is gone, so the file must go.
await new Promise((r) => server.close(r));
file = seed(origin);
await runBuildStart();
assert.equal(existsSync(file), false, 'stale dev manifest must be removed');

// A file we cannot vouch for is treated as stale, not as live.
file = seed(null, 'not json');
await runBuildStart();
assert.equal(existsSync(file), false, 'unparseable dev manifest must be removed');

rmSync(root, { recursive: true, force: true });
console.log('dev-manifest-preserved: OK');
