import { cacheSnapshot, readSnapshot } from '@/src/lib/offline/db';
import { clearSession, getToken } from '@/src/lib/storage';

const FETCH_TIMEOUT_MS = 15000;
/** Questions / AI answers need embedding + generation. */
export const LONG_REQUEST_TIMEOUT_MS = 90_000;

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code?: string,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

export type ApiRequestOptions = RequestInit & { timeoutMs?: number };

function baseUrl(): string {
  return process.env.EXPO_PUBLIC_API_BASE_URL ?? 'https://ssk-book.yabisoo.com';
}

let unauthorizedHandler: (() => void) | null = null;

export function setUnauthorizedHandler(handler: (() => void) | null): void {
  unauthorizedHandler = handler;
}

function canSnapshot(path: string, method: string): boolean {
  if (method !== 'GET') return false;
  if (path.includes('/reading/pack')) return false;
  return (
    path.startsWith('/api/library') ||
    path.startsWith('/api/me/dashboard') ||
    path.startsWith('/api/books/') ||
    path === '/api/quizzes' ||
    path.startsWith('/api/me/bookmarks') ||
    path.startsWith('/api/me/notes') ||
    path.startsWith('/api/me/highlights') ||
    path === '/api/auth/me'
  );
}

export async function apiRequest<T>(path: string, options: ApiRequestOptions = {}): Promise<T> {
  const { timeoutMs = FETCH_TIMEOUT_MS, signal: outerSignal, ...fetchOptions } = options;
  const headers = new Headers(fetchOptions.headers);
  headers.set('Accept', 'application/json');
  if (fetchOptions.body && !(fetchOptions.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json');
  }
  const token = getToken();
  if (token) headers.set('Authorization', `Bearer ${token}`);

  const method = (fetchOptions.method ?? 'GET').toUpperCase();
  const controller = new AbortController();
  const onOuterAbort = () => controller.abort();
  if (outerSignal) {
    if (outerSignal.aborted) controller.abort();
    else outerSignal.addEventListener('abort', onOuterAbort, { once: true });
  }
  const timer = setTimeout(() => controller.abort(), timeoutMs);

  let response: Response;
  let aborted = false;
  try {
    response = await fetch(`${baseUrl()}${path}`, {
      ...fetchOptions,
      headers,
      signal: controller.signal,
    });
  } catch (err) {
    aborted = controller.signal.aborted;
    if (canSnapshot(path, method)) {
      const cached = await readSnapshot<T>(path);
      if (cached !== null) return cached;
    }
    if (aborted && !outerSignal?.aborted) {
      throw new ApiError('La requête a pris trop de temps. Réessayez.', 0, 'TIMEOUT');
    }
    throw new ApiError('Impossible de joindre le serveur. Vérifiez votre connexion.', 0, 'NETWORK_ERROR');
  } finally {
    clearTimeout(timer);
    outerSignal?.removeEventListener('abort', onOuterAbort);
  }

  if (response.status === 204) return undefined as T;

  const raw = await response.text();
  let body: { data?: T; error?: { code?: string; message?: string } } & T = {} as never;
  if (raw) {
    try {
      body = JSON.parse(raw) as typeof body;
    } catch {
      body = {} as never;
    }
  }

  if (!response.ok) {
    if ((response.status === 401 || response.status === 403) && token) {
      await clearSession();
      unauthorizedHandler?.();
    }
    throw new ApiError(
      body.error?.message ?? 'Une erreur est survenue.',
      response.status,
      body.error?.code,
    );
  }

  if (canSnapshot(path, method) && body) {
    try {
      await cacheSnapshot(path, body);
    } catch {
      /* snapshots are optional; a large payload must not fail the request */
    }
  }

  return body as T;
}
