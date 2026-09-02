import { create } from 'zustand';
import { fetchCurrentCustomer, loginCustomer, logoutCustomer, registerCustomer } from '../api/auth';
import { ApiError, setUnauthorizedHandler } from '../api/client';
import { Customer, LoginPayload, RegisterPayload } from '../types/auth';
import { setSessionAuthenticated, isSessionAuthenticated } from '../utils/authSession';
import { isWaiter } from '../utils/roles';
import { useCartStore } from './cartStore';
import { useFavouriteStore } from './favouriteStore';

type AuthStatus = 'idle' | 'initializing' | 'authenticated' | 'guest';

let bootstrapPromise: Promise<boolean> | null = null;

interface AuthState {
  status: AuthStatus;
  customer: Customer | null;
  hasBootstrapped: boolean;
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
  set({ status: 'guest', customer: null, hasBootstrapped: true });
  useCartStore.getState().hydrateGuest();
  useFavouriteStore.getState().reset();
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
    mergedGuestCart = false;
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
  bootstrap: async () => {
    if (get().status === 'authenticated') {
      return true;
    }

    if (get().hasBootstrapped) {
      return false;
    }

    setUnauthorizedHandler(() => {
      // Definitive 401 from an authenticated API call — clear client session only.
      if (get().status === 'authenticated' || isSessionAuthenticated()) {
        resetCustomerSession(set);
      }
    });

    if (bootstrapPromise) {
      return bootstrapPromise;
    }

    set({ status: 'initializing' });

    bootstrapPromise = (async () => {
      try {
        const response = await fetchCurrentCustomer();
        set({ status: 'authenticated', customer: response.data, hasBootstrapped: true });
        await hydrateAuthenticatedSession(response.data);

        return true;
      } catch (error) {
        // Only treat definitive unauthenticated responses as logout.
        // Network / 5xx / CSRF races must not wipe a valid session.
        if (error instanceof ApiError && error.status === 401) {
          resetCustomerSession(set);

          return false;
        }

        set({ status: 'guest', customer: null, hasBootstrapped: true });

        return false;
      } finally {
        bootstrapPromise = null;
      }
    })();

    return bootstrapPromise;
  },
  login: async (payload) => {
    const response = await loginCustomer(payload);
    set({ status: 'authenticated', customer: response.data, hasBootstrapped: true });
    const mergedGuestCart = await hydrateAuthenticatedSession(response.data);

    return { customer: response.data, mergedGuestCart };
  },
  register: async (payload) => {
    const response = await registerCustomer(payload);
    set({ status: 'authenticated', customer: response.data, hasBootstrapped: true });
    const mergedGuestCart = await hydrateAuthenticatedSession(response.data);

    return { customer: response.data, mergedGuestCart };
  },
  logout: async () => {
    await logoutCustomer();
    resetCustomerSession(set);
  },
  syncCustomer: (customer) => {
    setSessionAuthenticated(true);
    set({ status: 'authenticated', customer, hasBootstrapped: true });
  },
  clearAuth: () => {
    resetCustomerSession(set);
  },
  setGuest: () => {
    resetCustomerSession(set);
  },
}));
