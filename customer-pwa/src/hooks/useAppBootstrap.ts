import { useEffect } from 'react';
import { useAuthStore } from '../stores/authStore';
import { useCartStore } from '../stores/cartStore';

export function useAppBootstrap(): void {
  const bootstrapAuth = useAuthStore((state) => state.bootstrap);
  const refreshCartCount = useCartStore((state) => state.refreshCount);

  useEffect(() => {
    void bootstrapAuth().then((isAuthenticated) => {
      if (isAuthenticated) {
        void refreshCartCount();
      }
    });
  }, [bootstrapAuth, refreshCartCount]);
}
