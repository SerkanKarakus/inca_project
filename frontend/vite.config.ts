import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
    },
  },
  server: { port: 5173 },
  test: {
    // Vitest test dosyaları bu pattern ile aranır
    include: ['src/**/*.test.{js,ts,jsx,tsx}'],
    globals: true,
    environment: 'jsdom',
  },
});
