import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';

/**
 * Relative base: chunks, preloads and the PDFium wasm resolve from the URL of pdf-editor.js, so the same build works
 * under /bundles/nowopdfeditor/build/ (NowoPdfEditorBundle) and /bundles/pdfeditor/build/ (deprecated PdfEditorBundle class).
 */
const ASSET_BASE = './';

/**
 * Production build for `assets:install` (REQ-ASSETS-001).
 * Demo dev uses Pentatrion Vite — see `demo/symfony8/vite.config.ts`.
 */
export default defineConfig({
  base: ASSET_BASE,
  plugins: [react()],
  build: {
    outDir: resolve(__dirname, 'src/Resources/public/build'),
    emptyOutDir: true,
    rollupOptions: {
      input: resolve(__dirname, 'src/Resources/assets/src/main.tsx'),
      output: {
        entryFileNames: 'pdf-editor.js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: 'pdf-editor.[ext]',
      },
    },
  },
});
