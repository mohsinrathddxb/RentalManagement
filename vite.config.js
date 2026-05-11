import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

const phpBackend = 'http://localhost/Rental-house-management-system';

export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/admin': phpBackend,
      '/api': phpBackend,
      '/functions': phpBackend,
      '/Rental-house-management-system': 'http://localhost',
    },
  },
});
