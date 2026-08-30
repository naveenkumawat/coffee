import { ImgHTMLAttributes, useEffect, useState } from 'react';
import { resolveCatalogMediaUrl, pickProductImage } from '../../utils/images';

interface ProductImageProps extends Omit<ImgHTMLAttributes<HTMLImageElement>, 'src' | 'alt'> {
  name: string;
  imagePath?: string | null;
  alt?: string;
  fit?: 'contain' | 'cover';
  eager?: boolean;
}

export function ProductImage({
  name,
  imagePath = null,
  alt,
  fit = 'contain',
  eager = false,
  className = '',
  ...imgProps
}: ProductImageProps) {
  const fallback = pickProductImage(name);
  const resolved = resolveCatalogMediaUrl(imagePath, fallback);
  const [src, setSrc] = useState(resolved);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    setSrc(resolveCatalogMediaUrl(imagePath, pickProductImage(name)));
    setFailed(false);
  }, [imagePath, name]);

  return (
    <img
      {...imgProps}
      src={src}
      alt={alt ?? name}
      loading={eager ? 'eager' : 'lazy'}
      decoding="async"
      className={`product-media ${fit === 'cover' ? 'is-cover' : 'is-contain'} ${failed ? 'is-fallback' : ''} ${className}`.trim()}
      onError={() => {
        const nextFallback = pickProductImage(name);

        if (src !== nextFallback) {
          setSrc(nextFallback);
          setFailed(true);
        }
      }}
    />
  );
}
