import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: resolve(__dirname, '../src/Resources/public/build'),
    emptyOutDir: true,
    rollupOptions: {
      input: resolve(__dirname, 'src/main.tsx'),
      output: {
        entryFileNames: 'pdf-editor.js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: 'pdf-editor.[ext]',
      },
    },
  },
});
