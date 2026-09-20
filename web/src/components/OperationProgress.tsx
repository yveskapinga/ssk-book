import { Spinner } from './Spinner'

type OperationProgressProps = {
  title: string
  detail?: string
  current?: number
  total?: number
  /** When true, shows an animated bar without a known percentage. */
  indeterminate?: boolean
  done?: boolean
}

export function OperationProgress({ title, detail, current = 0, total = 0, indeterminate = false, done = false }: OperationProgressProps) {
  const safeTotal = Math.max(0, total)
  const safeCurrent = Math.max(0, Math.min(current, safeTotal || current))
  const percent = !indeterminate && safeTotal > 0 ? Math.round((safeCurrent / safeTotal) * 100) : null

  return (
    <div className={`operation-progress ${done ? 'is-done' : ''}`} role="status" aria-live="polite">
      <div className="operation-progress-head">
        {!done && <Spinner size="sm" />}
        <div>
          <strong>{title}</strong>
          {detail && <p>{detail}</p>}
        </div>
        <span className="operation-progress-meter">
          {done ? 'Terminé' : percent !== null ? `${percent} %` : 'En cours'}
        </span>
      </div>
      <div
        className={`operation-progress-track ${indeterminate && !done ? 'is-indeterminate' : ''}`}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={percent ?? undefined}
        role="progressbar"
      >
        <span style={percent !== null ? { width: `${percent}%` } : undefined} />
      </div>
      {!indeterminate && safeTotal > 0 && (
        <small>{safeCurrent} / {safeTotal}</small>
      )}
    </div>
  )
}
