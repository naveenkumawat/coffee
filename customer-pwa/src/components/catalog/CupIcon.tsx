interface CupIconProps {
  kind: 'small' | 'large';
  className?: string;
}

/** Lightweight cup glyph — large kind renders taller for visual hierarchy. */
export function CupIcon({ kind, className = '' }: CupIconProps) {
  const isLarge = kind === 'large';

  return (
    <svg
      className={`cup-icon cup-icon-${kind} ${className}`.trim()}
      viewBox="0 0 24 24"
      width={isLarge ? 18 : 14}
      height={isLarge ? 18 : 14}
      aria-hidden="true"
      focusable="false"
    >
      <path
        fill="currentColor"
        d={
          isLarge
            ? 'M5.2 6.2h11.1l-.7 9.1c-.1 1.2-1.1 2.1-2.3 2.1H8.2c-1.2 0-2.2-.9-2.3-2.1L5.2 6.2zm12.3 0h1.1c1.3 0 2.3 1.1 2.2 2.4l-.3 2.2c-.2 1.2-1.2 2.1-2.4 2.1h-.8l.2-6.7zM8.5 3.8h7l.3 1.6H8.2L8.5 3.8z'
            : 'M6.2 7h9.1l-.55 7.2c-.08.95-.88 1.7-1.84 1.7H8.6c-.96 0-1.76-.75-1.84-1.7L6.2 7zm9.9 0h.85c1 0 1.8.85 1.72 1.85l-.2 1.55c-.12.9-.9 1.55-1.8 1.55h-.55L16.1 7zM8.7 5h6.4l.2 1.2H8.5L8.7 5z'
        }
      />
    </svg>
  );
}
