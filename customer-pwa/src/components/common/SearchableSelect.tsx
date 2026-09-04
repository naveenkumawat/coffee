import { useEffect, useId, useMemo, useRef, useState } from 'react';

export interface SearchableSelectOption {
  value: string;
  label: string;
  disabled?: boolean;
}

interface SearchableSelectProps {
  id?: string;
  name?: string;
  value: string;
  options: SearchableSelectOption[];
  placeholder?: string;
  disabled?: boolean;
  invalid?: boolean;
  allowClear?: boolean;
  searchPlaceholder?: string;
  emptyLabel?: string;
  onChange: (value: string) => void;
}

/**
 * Accessible type-to-search select for dynamic entity lists.
 * Tiny static enums should keep a native <select>.
 */
export function SearchableSelect({
  id,
  name,
  value,
  options,
  placeholder = 'Select…',
  disabled = false,
  invalid = false,
  allowClear = false,
  searchPlaceholder = 'Search…',
  emptyLabel = 'No matches',
  onChange,
}: SearchableSelectProps) {
  const generatedId = useId();
  const fieldId = id ?? generatedId;
  const listId = `${fieldId}-listbox`;
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [activeIndex, setActiveIndex] = useState(0);
  const rootRef = useRef<HTMLDivElement>(null);
  const searchRef = useRef<HTMLInputElement>(null);

  const selected = options.find((option) => option.value === value) ?? null;

  const filtered = useMemo(() => {
    const needle = query.trim().toLowerCase();

    if (needle === '') {
      return options.filter((option) => !option.disabled);
    }

    return options.filter(
      (option) => !option.disabled && option.label.toLowerCase().includes(needle),
    );
  }, [options, query]);

  useEffect(() => {
    if (!open) {
      return;
    }

    searchRef.current?.focus();
    setActiveIndex(0);

    const onPointerDown = (event: MouseEvent): void => {
      if (!rootRef.current?.contains(event.target as Node)) {
        setOpen(false);
        setQuery('');
      }
    };

    document.addEventListener('mousedown', onPointerDown);

    return () => document.removeEventListener('mousedown', onPointerDown);
  }, [open]);

  function choose(next: string): void {
    onChange(next);
    setOpen(false);
    setQuery('');
  }

  return (
    <div className="searchable-select" ref={rootRef}>
      {name ? <input type="hidden" name={name} value={value} /> : null}
      <button
        id={fieldId}
        type="button"
        className={`coffee-input searchable-select-trigger${invalid ? ' is-invalid' : ''}`.trim()}
        disabled={disabled}
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-controls={listId}
        onClick={() => {
          if (!disabled) {
            setOpen((current) => !current);
          }
        }}
      >
        <span>{selected?.label ?? placeholder}</span>
        <i className={`bi ${open ? 'bi-chevron-up' : 'bi-chevron-down'}`} aria-hidden="true"></i>
      </button>

      {open ? (
        <div className="searchable-select-menu" id={listId} role="listbox">
          <div className="searchable-select-search">
            <input
              ref={searchRef}
              type="search"
              className="coffee-input"
              value={query}
              placeholder={searchPlaceholder}
              aria-label={searchPlaceholder}
              onChange={(event) => {
                setQuery(event.target.value);
                setActiveIndex(0);
              }}
              onKeyDown={(event) => {
                if (event.key === 'ArrowDown') {
                  event.preventDefault();
                  setActiveIndex((index) => Math.min(index + 1, Math.max(filtered.length - 1, 0)));
                }

                if (event.key === 'ArrowUp') {
                  event.preventDefault();
                  setActiveIndex((index) => Math.max(index - 1, 0));
                }

                if (event.key === 'Enter') {
                  event.preventDefault();
                  const option = filtered[activeIndex];

                  if (option) {
                    choose(option.value);
                  }
                }

                if (event.key === 'Escape') {
                  event.preventDefault();
                  setOpen(false);
                  setQuery('');
                }
              }}
            />
          </div>
          {allowClear && value !== '' ? (
            <button type="button" className="searchable-select-option" role="option" onClick={() => choose('')}>
              Clear selection
            </button>
          ) : null}
          {filtered.length === 0 ? (
            <div className="searchable-select-empty">{emptyLabel}</div>
          ) : (
            filtered.map((option, index) => (
              <button
                key={option.value}
                type="button"
                role="option"
                aria-selected={option.value === value}
                className={`searchable-select-option${index === activeIndex ? ' is-active' : ''}`.trim()}
                onMouseEnter={() => setActiveIndex(index)}
                onClick={() => choose(option.value)}
              >
                {option.label}
              </button>
            ))
          )}
        </div>
      ) : null}
    </div>
  );
}
