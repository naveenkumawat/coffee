import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';

/** Non-secret build stamp for chunk recovery / debug (changes each build by default). */
const buildId = Date.now().toString(36);

function assertProductionApiBase(apiBase: string, enforceStrict: boolean): void {
  const value = apiBase.trim().replace(/\/$/, '');

  if (!value) {
    throw new Error(
      'Production builds require VITE_API_BASE_URL (absolute URL ending in /api/v1).',
    );
  }

  let url: URL;

  try {
    url = new URL(value);
  } catch {
    throw new Error(`VITE_API_BASE_URL is not a valid absolute URL: ${value}`);
  }

  if (!url.pathname.replace(/\/$/, '').endsWith('/api/v1')) {
    throw new Error('VITE_API_BASE_URL must end with /api/v1');
  }

  if (!enforceStrict) {
    return;
  }

  if (url.protocol !== 'https:') {
    throw new Error('Strict production builds require https:// VITE_API_BASE_URL');
  }

  const host = url.hostname.toLowerCase();
  const isPrivate =
    host === 'localhost'
    || host === '127.0.0.1'
    || host === '::1'
    || /^10\./.test(host)
    || /^192\.168\./.test(host)
    || /^172\.(1[6-9]|2\d|3[0-1])\./.test(host);

  if (isPrivate) {
    throw new Error('Strict production builds must not use localhost or private LAN hosts');
  }
}

export default defineConfig(({ mode }) => {
  // Vite runs with customer-pwa as cwd; '.' resolves .env / .env.production there.
  const env = loadEnv(mode, '.', '');

  if (mode === 'production') {
    assertProductionApiBase(
      env.VITE_API_BASE_URL ?? '',
      env.VITE_ENFORCE_PRODUCTION_API === '1',
    );
  }

  return {
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
  };
});
