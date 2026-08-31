import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

/** Non-secret build stamp for chunk recovery / debug (changes each build by default). */
const buildId = Date.now().toString(36);

export default defineConfig({
  plugins: [react()],
  define: {
    'import.meta.env.VITE_APP_BUILD_ID': JSON.stringify(buildId),
  },
  server: {
    host: '0.0.0.0',
    port: 4173,
  },
  preview: {
    host: '0.0.0.0',
    port: 4173,
  },
});
