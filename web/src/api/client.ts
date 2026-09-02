export type ApiErrorBody = { error?: { message?: string; correlationId?: string } }

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? ''

export class ApiError extends Error {
  constructor(message: string, public readonly status: number, public readonly correlationId?: string) {
    super(message)
  }
}

export async function apiRequest<T>(path: string, options: RequestInit = {}, token?: string | null): Promise<T> {
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')
  if (options.body && !(options.body instanceof FormData)) headers.set('Content-Type', 'application/json')
  if (token) headers.set('Authorization', `Bearer ${token}`)

  const response = await fetch(`${API_BASE_URL}${path}`, { ...options, headers })
  if (response.status === 204) return undefined as T

  const body = await response.json().catch(() => ({})) as ApiErrorBody & T
  if (!response.ok) {
    const message = body.error?.message ?? 'Une erreur est survenue.'
    const correlationId = body.error?.correlationId
    throw new ApiError(correlationId ? `${message} (${correlationId})` : message, response.status, correlationId)
  }

  return body as T
}
