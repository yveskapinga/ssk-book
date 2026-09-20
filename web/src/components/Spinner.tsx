type SpinnerProps = {
  size?: 'sm' | 'md'
  label?: string
}

export function Spinner({ size = 'sm', label }: SpinnerProps) {
  return (
    <span className={`ui-spinner ui-spinner-${size}`} role="status" aria-live="polite">
      <span className="ui-spinner-disc" aria-hidden="true" />
      {label ? <span className="ui-spinner-label">{label}</span> : <span className="sr-only">Chargement</span>}
    </span>
  )
}
