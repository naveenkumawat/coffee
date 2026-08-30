import { create } from 'zustand';
import { fetchCurrentCustomer, loginCustomer, logoutCustomer, registerCustomer } from '../api/auth';
import { ApiError, setUnauthorizedHandler } from '../api/client';
import { Customer, LoginPayload, RegisterPayload } from '../types/auth';
import { useCartStore } from './cartStore';
import { useFavouriteStore } from './favouriteStore';

type AuthStatus = 'idle' | 'initializing' | 'authenticated' | 'guest';

let bootstrapPromise: Promise<boolean> | null = null;

interface AuthState {
  status: AuthStatus;
  customer: Customer | null;
  hasBootstrapped: boolean;
  bootstrap: () => Promise<boolean>;
  login: (payload: LoginPayload) => Promise<Customer>;
  register: (payload: RegisterPayload) => Promise<Customer>;
  logout: () => Promise<void>;
  syncCustomer: (customer: Customer) => void;
  clearAuth: () => void;
  setGuest: () => void;
}

function resetCustomerSession(set: (value: Partial<AuthState>) => void): void {
  set({ status: 'guest', customer: null, hasBootstrapped: true });
  useCartStore.getState().reset();
  useFavouriteStore.getState().reset();
}

async function hydrateCustomerSession(): Promise<void> {
  await Promise.all([
    useCartStore.getState().refreshCount(),
    useFavouriteStore.getState().refreshIds()
  ]);
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
      resetCustomerSession(set);
    });

    if (bootstrapPromise) {
      return bootstrapPromise;
    }

    set({ status: 'initializing' });

    bootstrapPromise = (async () => {
      try {
        const response = await fetchCurrentCustomer();
        set({ status: 'authenticated', customer: response.data, hasBootstrapped: true });
        await hydrateCustomerSession();

        return true;
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
          resetCustomerSession(set);

          return false;
        }

        resetCustomerSession(set);

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
    await hydrateCustomerSession();

    return response.data;
  },
  register: async (payload) => {
    const response = await registerCustomer(payload);
    set({ status: 'authenticated', customer: response.data, hasBootstrapped: true });
    await hydrateCustomerSession();

    return response.data;
  },
  logout: async () => {
    await logoutCustomer();
    resetCustomerSession(set);
  },
  syncCustomer: (customer) => {
    set({ status: 'authenticated', customer, hasBootstrapped: true });
  },
  clearAuth: () => {
    resetCustomerSession(set);
  },
  setGuest: () => {
    resetCustomerSession(set);
  }
}));
