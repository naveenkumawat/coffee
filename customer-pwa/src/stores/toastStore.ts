import { create } from 'zustand';

export type ToastVariant = 'success' | 'error' | 'info';

export interface ToastMessage {
  id: string;
  message: string;
  variant: ToastVariant;
}

interface ToastState {
  toasts: ToastMessage[];
  push: (message: string, variant?: ToastVariant, durationMs?: number) => string;
  success: (message: string, durationMs?: number) => string;
  error: (message: string, durationMs?: number) => string;
  dismiss: (id: string) => void;
  clear: () => void;
}

let toastSeq = 0;

export const useToastStore = create<ToastState>((set, get) => ({
  toasts: [],
  push: (message, variant = 'info', durationMs = 3200) => {
    const id = `toast-${Date.now()}-${toastSeq++}`;

    set((state) => ({
      toasts: [...state.toasts.slice(-2), { id, message, variant }],
    }));

    window.setTimeout(() => {
      get().dismiss(id);
    }, durationMs);

    return id;
  },
  success: (message, durationMs) => get().push(message, 'success', durationMs),
  error: (message, durationMs) => get().push(message, 'error', durationMs),
  dismiss: (id) => {
    set((state) => ({
      toasts: state.toasts.filter((toast) => toast.id !== id),
    }));
  },
  clear: () => set({ toasts: [] }),
}));
