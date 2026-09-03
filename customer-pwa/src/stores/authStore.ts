import { create } from 'zustand';
import { fetchCurrentCustomer, loginCustomer, logoutCustomer, registerCustomer } from '../api/auth';
import { ApiError, setUnauthorizedHandler } from '../api/client';
import { realtimeConnection } from '../realtime/RealtimeConnection';
import { Customer, LoginPayload, RegisterPayload } from '../types/auth';
import { setSessionAuthenticated, isSessionAuthenticated } from '../utils/authSession';
import { isWaiter } from '../utils/roles';
import { useCartStore } from './cartStore';
import { useFavouriteStore } from './favouriteStore';
import { useNotificationStore } from './notificationStore';
import { useToastStore } from './toastStore';

type AuthStatus = 'idle' | 'initializing' | 'authenticated' | 'guest' | 'session_unknown';

let bootstrapPromise: Promise<boolean> | null = null;
let unauthorizedToastAt = 0;

interface AuthState {
  status: AuthStatus;
  customer: Customer | null;
  hasBootstrapped: boolean;
  sessionCheckFailed: boolean;
  bootstrap: () => Promise<boolean>;
  login: (payload: LoginPayload) => Promise<{ customer: Customer; mergedGuestCart: boolean }>;
  register: (payload: RegisterPayload) => Promise<{ customer: Customer; mergedGuestCart: boolean }>;
  logout: () => Promise<void>;
  syncCustomer: (customer: Customer) => void;
  clearAuth: () => void;
  setGuest: () => void;
}

function resetCustomerSession(set: (value: Partial<AuthState>) => void): void {
  setSessionAuthenticated(false);
  set({
    status: 'guest',
    customer: null,
    hasBootstrapped: true,
    sessionCheckFailed: false,
  });
  useCartStore.getState().hydrateGuest();
  useFavouriteStore.getState().reset();
  useNotificationStore.getState().reset();
}

async function hydrateAuthenticatedSession(customer: Customer): Promise<boolean> {
  setSessionAuthenticated(true);

  if (isWaiter(customer)) {
    return false;
  }

  let mergedGuestCart = false;

  try {
    mergedGuestCart = await useCartStore.getState().mergeGuestCart();
  } catch {
    // Keep guest cart local — do not clear on transient merge failure.
    mergedGuestCart = false;
    useToastStore.getState().error('Signed in, but your guest cart could not be merged yet. Try again from Cart.');
  }

  await Promise.all([
    useCartStore.getState().loadCart(),
    useFavouriteStore.getState().refreshIds(),
  ]);

  return mergedGuestCart;
}

export const useAuthStore = create<AuthState>((set, get) => ({
  status: 'idle',
  customer: null,
  hasBootstrapped: false,
  sessionCheckFailed: false,
  bootstrap: async () => {
    if (get().status === 'authenticated') {
      return true;
    }

    if (get().hasBootstrapped && get().status !== 'session_unknown') {
      return false;
    }

    setUnauthorizedHandler(() => {
      // Definitive 401 from an authenticated API call — clear client session only.
      if (get().status === 'authenticated' || isSessionAuthenticated()) {
        resetCustomerSession(set);
        const now = Date.now();

        if (now - unauthorizedToastAt > 4000) {
          unauthorizedToastAt = now;
          useToastStore.getState().error('Session expired. Sign in again to continue.');
        }
      }
    });

    if (bootstrapPromise) {
      return bootstrapPromise;
    }

    set({ status: 'initializing', sessionCheckFailed: false });

    bootstrapPromise = (async () => {
      try {
        const response = await fetchCurrentCustomer();
        set({
          status: 'authenticated',
          customer: response.data,
          hasBootstrapped: true,
          sessionCheckFailed: false,
        });
        await hydrateAuthenticatedSession(response.data);

        return true;
      } catch (error) {
        // Only treat definitive unauthenticated responses as logout.
        // Network / 5xx / CSRF races must not wipe a valid session.
        if (error instanceof ApiError && error.status === 401) {
          resetCustomerSession(set);

          return false;
        }

        set({
          status: 'session_unknown',
          customer: null,
          hasBootstrapped: false,
          sessionCheckFailed: true,
        });

        return false;
      } finally {
        bootstrapPromise = null;
      }
    })();

    return bootstrapPromise;
  },
  login: async (payload) => {
    const response = await loginCustomer(payload);
    set({
      status: 'authenticated',
      customer: response.data,
      hasBootstrapped: true,
      sessionCheckFailed: false,
    });
    const mergedGuestCart = await hydrateAuthenticatedSession(response.data);

    return { customer: response.data, mergedGuestCart };
  },
  register: async (payload) => {
    const response = await registerCustomer(payload);
    set({
      status: 'authenticated',
      customer: response.data,
      hasBootstrapped: true,
      sessionCheckFailed: false,
    });
    const mergedGuestCart = await hydrateAuthenticatedSession(response.data);

    return { customer: response.data, mergedGuestCart };
  },
  logout: async () => {
    realtimeConnection.disconnect();
    await logoutCustomer();
    resetCustomerSession(set);
  },
  syncCustomer: (customer) => {
    setSessionAuthenticated(true);
    set({
      status: 'authenticated',
      customer,
      hasBootstrapped: true,
      sessionCheckFailed: false,
    });
  },
  clearAuth: () => {
    realtimeConnection.disconnect();
    resetCustomerSession(set);
  },
  setGuest: () => {
    realtimeConnection.disconnect();
    resetCustomerSession(set);
  },
}));
