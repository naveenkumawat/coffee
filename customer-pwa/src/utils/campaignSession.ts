const SESSION_KEY = 'coffee.campaign-session.v1';

export function getOrCreateCampaignSessionKey(): string {
  if (typeof window === 'undefined') {
    return 'ssr';
  }

  try {
    const existing = window.sessionStorage.getItem(SESSION_KEY);

    if (existing && /^[A-Za-z0-9_-]+$/.test(existing)) {
      return existing;
    }

    const next = `s${Math.random().toString(36).slice(2)}${Date.now().toString(36)}`;
    window.sessionStorage.setItem(SESSION_KEY, next);

    return next;
  } catch {
    return `s${Date.now().toString(36)}`;
  }
}
