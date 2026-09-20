import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import { ApiError } from '../api/client'

type Banner = {
  message: string
  correlationId?: string | null
}

type AppErrorContextValue = {
  report: (err: unknown, userMessage?: string) => void
  clear: () => void
}

const AppErrorContext = createContext<AppErrorContextValue | null>(null)
const AUTO_DISMISS_MS = 10000

function messageFromError(err: unknown, userMessage?: string): Banner {
  if (err instanceof ApiError) {
    return {
      message: userMessage || err.message || 'Une erreur est survenue.',
      correlationId: err.correlationId,
    }
  }
  if (err instanceof Error) {
    return { message: userMessage || err.message || 'Une erreur est survenue.' }
  }
  return { message: userMessage || 'Une erreur est survenue.' }
}

export function AppErrorProvider({ children }: { children: ReactNode }) {
  const [banner, setBanner] = useState<Banner | null>(null)
  const clear = useCallback(() => setBanner(null), [])

  const report = useCallback((err: unknown, userMessage?: string) => {
    setBanner(messageFromError(err, userMessage))
  }, [])

  useEffect(() => {
    if (!banner) return
    const id = window.setTimeout(() => setBanner(null), AUTO_DISMISS_MS)
    return () => window.clearTimeout(id)
  }, [banner])

  useEffect(() => {
    const onRejection = (event: PromiseRejectionEvent) => {
      report(event.reason, 'Une erreur inattendue est survenue.')
    }
    const onError = (event: ErrorEvent) => {
      report(event.error ?? event.message, 'Une erreur inattendue est survenue.')
    }
    window.addEventListener('unhandledrejection', onRejection)
    window.addEventListener('error', onError)
    return () => {
      window.removeEventListener('unhandledrejection', onRejection)
      window.removeEventListener('error', onError)
    }
  }, [report])

  const value = useMemo(() => ({ report, clear }), [report, clear])

  return (
    <AppErrorContext.Provider value={value}>
      {children}
      {banner ? (
        <div className="app-error-banner" role="alert" aria-live="assertive" data-testid="app-error">
          <div>
            <p>{banner.message}</p>
            {banner.correlationId ? <small>Réf. <code>{banner.correlationId}</code></small> : null}
          </div>
          <button type="button" className="ghost" onClick={clear} aria-label="Fermer">×</button>
        </div>
      ) : null}
    </AppErrorContext.Provider>
  )
}

export function useAppError(): AppErrorContextValue {
  const ctx = useContext(AppErrorContext)
  if (!ctx) throw new Error('useAppError must be used within AppErrorProvider')
  return ctx
}
