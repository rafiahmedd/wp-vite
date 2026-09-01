/**
 * Example vite.config.js for a WordPress plugin
 *
 * Copy this to your plugin/theme root and adjust as needed.
 *
 * Install dependencies first:
 *   npm install -D vite @rafiahmedd/wp-vite
 *
 * For React support, also install:
 *   npm install -D @vitejs/plugin-react
 *   npm install react react-dom   (needed as dev deps by @vitejs/plugin-react)
 */

import { wpVite, wpScripts } from '@rafiahmedd/wp-vite';

// ─── Option A: Vanilla JS / Vue / Svelte ─────────────────────────────────────
export default {
    plugins: [
        wpVite({
            input: 'src/main.js',   // Or 'src/main.ts', 'src/main.vue', etc.
            outDir: 'dist',
        }),
    ],
};

// ─── Option B: React (with HMR Fast Refresh) ─────────────────────────────────
// import react from '@vitejs/plugin-react';
//
// export default {
//     plugins: [
//         wpVite({ input: 'src/main.jsx', outDir: 'dist' }),
//         react(),
//     ],
// };

// ─── Option C: React + externalise WP packages (smaller bundle) ──────────────
// The wpScripts() plugin removes react, react-dom, and @wordpress/* from your
// bundle — WordPress already provides them on the global scope.
//
// Remember to add 'react', 'react-dom', 'wp-element' etc. to the `dependencies`
// array when calling wp_vite_enqueue() on the PHP side.
//
// import react from '@vitejs/plugin-react';
//
// export default {
//     plugins: [
//         wpVite({ input: 'src/main.jsx', outDir: 'dist' }),
//         wpScripts(),   // externalise all WP globals
//         react(),
//     ],
// };

// ─── Option D: Multiple entry points ─────────────────────────────────────────
// export default {
//     plugins: [
//         wpVite({
//             input: {
//                 main:  'src/main.ts',
//                 admin: 'src/admin.ts',
//             },
//             outDir: 'dist',
//         }),
//     ],
// };

// ─── Option E: HTTPS dev server (e.g. to match a local SSL domain) ───────────
// import { readFileSync } from 'node:fs';
//
// export default {
//     plugins: [
//         wpVite({ input: 'src/main.ts', outDir: 'dist' }),
//     ],
//     server: {
//         host: 'my-plugin.local',
//         https: {
//             cert: readFileSync('path/to/cert.pem'),
//             key:  readFileSync('path/to/key.pem'),
//         },
//     },
// };
