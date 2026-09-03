import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, type Plugin } from 'vite';
import react from '@vitejs/plugin-react';
import symfonyPlugin from 'vite-plugin-symfony';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const dockerBundleAssets = '/var/pdf-editor-bundle/src/Resources/assets';
const bundleRoot = fs.existsSync(dockerBundleAssets)
  ? '/var/pdf-editor-bundle'
  : path.resolve(__dirname, '../..');
const bundleAssets = path.join(bundleRoot, 'src/Resources/assets');

/**
 * Copy `public/build/.vite/entrypoints.json` to `public/build/entrypoints.json`.
 * FrankenPHP workers can miss the nested file after rebuilds.
 */
function copyEntrypointsJson(): Plugin {
  return {
    name: 'copy-entrypoints-json',
    closeBundle() {
      const src = path.resolve(__dirname, 'public/build/.vite/entrypoints.json');
      const dest = path.resolve(__dirname, 'public/build/entrypoints.json');
      if (fs.existsSync(src)) {
        fs.copyFileSync(src, dest);
      }
    },
  };
}

/** Idiomorph classic script — installed via pnpm, not jsDelivr. */
function copyIdiomorph(): Plugin {
  return {
    name: 'copy-idiomorph',
    closeBundle() {
      const src = path.resolve(__dirname, 'node_modules/idiomorph/dist/idiomorph.js');
      const destDir = path.resolve(__dirname, 'public/build/hot-reload');
      const dest = path.join(destDir, 'idiomorph.js');
      if (!fs.existsSync(src)) {
        throw new Error('idiomorph not installed — run pnpm install in demo/symfony8');
      }
      fs.mkdirSync(destDir, { recursive: true });
      fs.copyFileSync(src, dest);
    },
  };
}

/**
 * Pentatrion Vite (`vite-plugin-symfony` + `pentatrion/vite-bundle`).
 * Twig: `vite_entry_link_tags` / `vite_entry_script_tags('pdf-editor')`.
 */
export default defineConfig({
  plugins: [react(), symfonyPlugin(), copyIdiomorph(), copyEntrypointsJson()],
  resolve: {
    alias: {
      '@bundle': bundleAssets,
      react: path.join(bundleRoot, 'node_modules/react'),
      'react-dom': path.join(bundleRoot, 'node_modules/react-dom'),
      '@embedpdf/react-pdf-viewer': path.join(bundleRoot, 'node_modules/@embedpdf/react-pdf-viewer'),
      'pdf-lib': path.join(bundleRoot, 'node_modules/pdf-lib'),
    },
    extensions: ['.tsx', '.ts', '.js'],
  },
  build: {
    emptyOutDir: true,
    rollupOptions: {
      input: {
        'pdf-editor': './assets/pdf-editor.ts',
        'hot-reload/frankenphp-hot-reload': './assets/frankenphp-hot-reload.ts',
      },
      output: {
        entryFileNames: (chunk) =>
          chunk.name.startsWith('hot-reload/')
            ? `${chunk.name}.js`
            : 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
      },
    },
  },
  server: {
    fs: {
      allow: [bundleAssets, bundleRoot, __dirname],
    },
  },
});
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, type Plugin } from 'vite';
import react from '@vitejs/plugin-react';
import symfonyPlugin from 'vite-plugin-symfony';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const dockerBundleAssets = '/var/pdf-editor-bundle/src/Resources/assets';
const bundleRoot = fs.existsSync(dockerBundleAssets)
  ? '/var/pdf-editor-bundle'
  : path.resolve(__dirname, '../..');
const bundleAssets = path.join(bundleRoot, 'src/Resources/assets');

/**
 * Copy `public/build/.vite/entrypoints.json` to `public/build/entrypoints.json`.
 * FrankenPHP workers can miss the nested file after rebuilds.
 */
function copyEntrypointsJson(): Plugin {
  return {
    name: 'copy-entrypoints-json',
    closeBundle() {
      const src = path.resolve(__dirname, 'public/build/.vite/entrypoints.json');
      const dest = path.resolve(__dirname, 'public/build/entrypoints.json');
      if (fs.existsSync(src)) {
        fs.copyFileSync(src, dest);
      }
    },
  };
}

/** Idiomorph classic script — installed via pnpm, not jsDelivr. */
function copyIdiomorph(): Plugin {
  return {
    name: 'copy-idiomorph',
    closeBundle() {
      const src = path.resolve(__dirname, 'node_modules/idiomorph/dist/idiomorph.js');
      const destDir = path.resolve(__dirname, 'public/build/hot-reload');
      const dest = path.join(destDir, 'idiomorph.js');
      if (!fs.existsSync(src)) {
        throw new Error('idiomorph not installed — run pnpm install in demo/symfony8');
      }
      fs.mkdirSync(destDir, { recursive: true });
      fs.copyFileSync(src, dest);
    },
  };
}

/**
 * Pentatrion Vite (`vite-plugin-symfony` + `pentatrion/vite-bundle`).
 * Twig: `vite_entry_link_tags` / `vite_entry_script_tags('pdf-editor')`.
 */
export default defineConfig({
  plugins: [react(), symfonyPlugin(), copyIdiomorph(), copyEntrypointsJson()],
  resolve: {
    alias: {
      '@bundle': bundleAssets,
      react: path.join(bundleRoot, 'node_modules/react'),
      'react-dom': path.join(bundleRoot, 'node_modules/react-dom'),
      '@embedpdf/react-pdf-viewer': path.join(bundleRoot, 'node_modules/@embedpdf/react-pdf-viewer'),
      'pdf-lib': path.join(bundleRoot, 'node_modules/pdf-lib'),
    },
    extensions: ['.tsx', '.ts', '.js'],
  },
  build: {
    emptyOutDir: true,
    rollupOptions: {
      input: {
        'pdf-editor': './assets/pdf-editor.ts',
        'hot-reload/frankenphp-hot-reload': './assets/frankenphp-hot-reload.ts',
      },
      output: {
        entryFileNames: (chunk) =>
          chunk.name.startsWith('hot-reload/')
            ? `${chunk.name}.js`
            : 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
      },
    },
  },
  server: {
    fs: {
      allow: [bundleAssets, bundleRoot, __dirname],
    },
  },
});
