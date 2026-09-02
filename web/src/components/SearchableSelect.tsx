import { useEffect, useId, useMemo, useRef, useState, type KeyboardEvent } from 'react'

export type SelectOption = { value: string; label: string; hint?: string }

type Props = {
  value: string
  onChange: (value: string) => void
  options: SelectOption[]
  label?: string
  placeholder?: string
  searchPlaceholder?: string
  empty?: string
  allowEmpty?: boolean
  emptyLabel?: string
  required?: boolean
  disabled?: boolean
  name?: string
}

export function SearchableSelect({
  value, onChange, options, label, placeholder = 'Choisir…', searchPlaceholder = 'Rechercher…',
  empty = 'Aucun résultat', allowEmpty = false, emptyLabel = 'Aucun choix',
  required = false, disabled = false, name,
}: Props) {
  const id = useId()
  const root = useRef<HTMLDivElement>(null)
  const searchRef = useRef<HTMLInputElement>(null)
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [active, setActive] = useState(0)

  const items = useMemo(() => {
    const list = allowEmpty ? [{ value: '', label: emptyLabel }, ...options] : options
    const needle = query.trim().toLocaleLowerCase('fr')
    if (!needle) return list
    return list.filter(item => `${item.label} ${item.hint ?? ''} ${item.value}`.toLocaleLowerCase('fr').includes(needle))
  }, [allowEmpty, emptyLabel, options, query])

  const selected = options.find(item => item.value === value)

  useEffect(() => {
    function close(event: MouseEvent) {
      if (!root.current?.contains(event.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', close)
    return () => document.removeEventListener('mousedown', close)
  }, [])

  useEffect(() => {
    if (open) {
      setQuery('')
      setActive(0)
      requestAnimationFrame(() => searchRef.current?.focus())
    }
  }, [open])

  function choose(next: string) {
    onChange(next)
    setOpen(false)
  }

  function onKey(event: KeyboardEvent) {
    if (event.key === 'Escape') { setOpen(false); return }
    if (event.key === 'ArrowDown') { event.preventDefault(); setActive(i => Math.min(i + 1, items.length - 1)) }
    if (event.key === 'ArrowUp') { event.preventDefault(); setActive(i => Math.max(i - 1, 0)) }
    if (event.key === 'Enter' && items[active]) { event.preventDefault(); choose(items[active].value) }
  }

  return (
    <div className="combo" ref={root}>
      {label && <label className="combo-label" htmlFor={id}>{label}</label>}
      <input type="hidden" name={name} value={value} required={required} />
      <button
        type="button"
        id={id}
        className="combo-trigger"
        disabled={disabled}
        aria-haspopup="listbox"
        aria-expanded={open}
        onClick={() => !disabled && setOpen(v => !v)}
      >
        <span>{selected?.label || placeholder}</span>
        {selected?.hint && <small>{selected.hint}</small>}
      </button>
      {open && (
        <div className="combo-panel" role="listbox" onKeyDown={onKey}>
          <label className="combo-search"><span>⌕</span>
            <input ref={searchRef} value={query} onChange={e => setQuery(e.target.value)} placeholder={searchPlaceholder} aria-label={searchPlaceholder} />
          </label>
          <div className="combo-options">
            {items.map((item, index) =>
              <button
                type="button"
                key={`${item.value}-${index}`}
                role="option"
                aria-selected={item.value === value}
                className={`combo-option ${item.value === value ? 'selected' : ''} ${index === active ? 'active' : ''}`}
                onMouseEnter={() => setActive(index)}
                onClick={() => choose(item.value)}
              >
                <strong>{item.label}</strong>
                {item.hint && <small>{item.hint}</small>}
              </button>
            )}
            {items.length === 0 && <p className="combo-empty">{empty}</p>}
          </div>
        </div>
      )}
    </div>
  )
}
