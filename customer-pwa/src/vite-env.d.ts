/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_BASE_URL?: string;
  /** Injected at build time — non-secret stamp for chunk recovery. */
  readonly VITE_APP_BUILD_ID?: string;
  readonly VITE_REALTIME_ENABLED?: string;
  readonly VITE_REVERB_APP_KEY?: string;
  readonly VITE_REVERB_HOST?: string;
  readonly VITE_REVERB_PORT?: string;
  readonly VITE_REVERB_SCHEME?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}

declare module '*.wav' {
  const src: string;
  export default src;
}
