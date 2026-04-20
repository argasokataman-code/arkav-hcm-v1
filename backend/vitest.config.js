import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'jsdom',
    include: ['tests/ui/**/*.test.js'],
    setupFiles: ['tests/ui/vitest.setup.js'],
    clearMocks: true,
    restoreMocks: true,
  },
});
