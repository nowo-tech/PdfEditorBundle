import { defineConfig } from 'vitest/config';

/**
 * Vitest configuration for the editor's TypeScript unit tests (REQ-ASSETS-005 logger tests). The React editor itself
 * is covered by the strict type check and the demo; only the pure logger module is unit-tested here.
 */
export default defineConfig({
  test: {
    environment: 'jsdom',
    globals: true,
    include: ['src/Resources/assets/src/**/*.test.ts'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'text-summary'],
      reportsDirectory: './coverage-ts',
      include: ['src/Resources/assets/src/logger.ts'],
      exclude: ['**/*.test.ts', '**/node_modules/**'],
      thresholds: {
        lines: 90,
        statements: 90,
        functions: 90,
        branches: 90,
      },
    },
  },
});
