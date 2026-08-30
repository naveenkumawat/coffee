import { useLocation, useNavigate } from 'react-router-dom';
import { ApiError } from '../../api/client';
import { useAuthStore } from '../../stores/authStore';
import { useFavouriteStore } from '../../stores/favouriteStore';
import { useToastStore } from '../../stores/toastStore';
import { buildLoginRedirect } from '../../utils/navigation';

interface FavouriteToggleProps {
  productId: number;
  className?: string;
  size?: 'sm' | 'md';
}

export function FavouriteToggle({ productId, className = '', size = 'md' }: FavouriteToggleProps) {
  const status = useAuthStore((state) => state.status);
  const isFavourite = useFavouriteStore((state) => state.isFavourite(productId));
  const isPending = useFavouriteStore((state) => state.isPending(productId));
  const toggle = useFavouriteStore((state) => state.toggle);
  const toastSuccess = useToastStore((state) => state.success);
  const toastError = useToastStore((state) => state.error);
  const navigate = useNavigate();
  const location = useLocation();

  async function handleClick(): Promise<void> {
    if (status !== 'authenticated') {
      navigate(buildLoginRedirect(location.pathname, location.search));
      return;
    }

    const wasFavourite = isFavourite;

    try {
      await toggle(productId);
      toastSuccess(wasFavourite ? 'Removed from favourites' : 'Saved to favourites');
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        navigate(buildLoginRedirect(location.pathname, location.search));
        return;
      }

      toastError(error instanceof ApiError ? error.message : 'Unable to update favourites.');
    }
  }

  return (
    <button
      type="button"
      className={`favourite-toggle ${size === 'sm' ? 'is-sm' : ''} ${isFavourite ? 'is-active' : ''} ${className}`.trim()}
      aria-label={isFavourite ? 'Remove from favourites' : 'Add to favourites'}
      aria-pressed={isFavourite}
      disabled={isPending}
      onClick={() => void handleClick()}
    >
      <i className={`bi ${isFavourite ? 'bi-heart-fill' : 'bi-heart'}`} aria-hidden="true"></i>
    </button>
  );
}
