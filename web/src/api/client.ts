export type ApiErrorBody = { error?: { code?: string; message?: string; correlationId?: string } }

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? ''

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly correlationId?: string,
    public readonly code?: string,
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

export async function apiRequest<T>(path: string, options: RequestInit = {}, token?: string | null): Promise<T> {
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')
  if (options.body && !(options.body instanceof FormData)) headers.set('Content-Type', 'application/json')
  if (token) headers.set('Authorization', `Bearer ${token}`)

  let response: Response
  try {
    response = await fetch(`${API_BASE_URL}${path}`, { ...options, headers })
  } catch {
    throw new ApiError('Impossible de joindre le serveur. Vérifiez votre connexion.', 0, undefined, 'NETWORK_ERROR')
  }

  if (response.status === 204) return undefined as T

  const raw = await response.text()
  let body: ApiErrorBody & T = {} as ApiErrorBody & T
  if (raw) {
    try {
      body = JSON.parse(raw) as ApiErrorBody & T
    } catch {
      body = {} as ApiErrorBody & T
    }
  }

  if (!response.ok) {
    const message = body.error?.message ?? (response.status >= 500
      ? 'Une erreur interne est survenue.'
      : 'Une erreur est survenue.')
    const correlationId = body.error?.correlationId
    throw new ApiError(message, response.status, correlationId, body.error?.code)
  }

  return body as T
}
