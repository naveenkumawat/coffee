import { create } from 'zustand';
import { addFavourite, fetchFavouriteIds, removeFavourite } from '../api/favourites';
import { ApiError } from '../api/client';

interface FavouriteState {
  ids: number[];
  pendingIds: number[];
  hasLoaded: boolean;
  refreshIds: () => Promise<void>;
  isFavourite: (productId: number) => boolean;
  isPending: (productId: number) => boolean;
  toggle: (productId: number) => Promise<boolean>;
  reset: () => void;
}

export const useFavouriteStore = create<FavouriteState>((set, get) => ({
  ids: [],
  pendingIds: [],
  hasLoaded: false,
  refreshIds: async () => {
    try {
      const response = await fetchFavouriteIds();
      set({
        ids: response.data.ids,
        hasLoaded: true
      });
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        set({ ids: [], pendingIds: [], hasLoaded: true });
      }
    }
  },
  isFavourite: (productId) => get().ids.includes(productId),
  isPending: (productId) => get().pendingIds.includes(productId),
  toggle: async (productId) => {
    const currentlyFavourite = get().ids.includes(productId);
    const previousIds = get().ids;

    set({
      ids: currentlyFavourite
        ? previousIds.filter((id) => id !== productId)
        : [...previousIds, productId],
      pendingIds: [...get().pendingIds, productId]
    });

    try {
      if (currentlyFavourite) {
        await removeFavourite(productId);
      } else {
        await addFavourite(productId);
      }

      return !currentlyFavourite;
    } catch (error) {
      set({ ids: previousIds });
      throw error;
    } finally {
      set({
        pendingIds: get().pendingIds.filter((id) => id !== productId)
      });
    }
  },
  reset: () => {
    set({ ids: [], pendingIds: [], hasLoaded: false });
  }
}));
