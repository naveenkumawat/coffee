export type DiningPageView = 'loading' | 'active-session' | 'unavailable' | 'start';

/**
 * Render precedence for /dining. An active session always wins so a stale
 * loading flag cannot hide a seated customer after Admin disables new Dining.
 */
export function resolveDiningPageView(input: {
  loading: boolean;
  hasBootstrapped: boolean;
  hasActiveSession: boolean;
  diningEnabled: boolean;
}): DiningPageView {
  if (input.hasActiveSession) {
    return 'active-session';
  }

  if (input.loading || !input.hasBootstrapped) {
    return 'loading';
  }

  if (!input.diningEnabled) {
    return 'unavailable';
  }

  return 'start';
}

/** Hold the skeleton only until public bootstrap finishes and no session exists. */
export function diningPageShouldKeepLoading(hasBootstrapped: boolean, hasActiveSession: boolean): boolean {
  return !hasBootstrapped && !hasActiveSession;
}
