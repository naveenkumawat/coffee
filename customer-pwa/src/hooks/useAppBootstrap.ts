import { useEffect } from 'react';
import { useAuthStore } from '../stores/authStore';
import { useCartStore } from '../stores/cartStore';
import { useContentStore } from '../stores/contentStore';

export function useAppBootstrap(): void {
  const bootstrapAuth = useAuthStore((state) => state.bootstrap);
  const bootstrapContent = useContentStore((state) => state.bootstrap);
  const refreshCartCount = useCartStore((state) => state.refreshCount);

  useEffect(() => {
    void bootstrapContent();
    void bootstrapAuth().then((isAuthenticated) => {
      if (isAuthenticated) {
        void refreshCartCount();
      }
    });
  }, [bootstrapAuth, bootstrapContent, refreshCartCount]);
}
