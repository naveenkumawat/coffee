/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_BASE_URL?: string;
  /** Injected at build time — non-secret stamp for chunk recovery. */
  readonly VITE_APP_BUILD_ID?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
