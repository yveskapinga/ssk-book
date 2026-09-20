import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { Spinner } from './Spinner'

type BusyButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  busy?: boolean
  busyLabel?: string
  children: ReactNode
}

export function BusyButton({ busy = false, busyLabel, children, className = '', disabled, type = 'button', ...props }: BusyButtonProps) {
  return (
    <button
      type={type}
      className={`busy-button ${className}`.trim()}
      disabled={disabled || busy}
      aria-busy={busy || undefined}
      {...props}
    >
      {busy && <Spinner size="sm" />}
      <span>{busy ? (busyLabel ?? children) : children}</span>
    </button>
  )
}
