import { cacheSnapshot, readSnapshot } from '@/src/lib/offline/db';
import { clearSession, getToken } from '@/src/lib/storage';

const FETCH_TIMEOUT_MS = 8000;

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

function baseUrl(): string {
  return process.env.EXPO_PUBLIC_API_BASE_URL ?? 'https://ssk-book.solutic.app';
}

let unauthorizedHandler: (() => void) | null = null;

export function setUnauthorizedHandler(handler: (() => void) | null): void {
  unauthorizedHandler = handler;
}

function canSnapshot(path: string, method: string): boolean {
  if (method !== 'GET') return false;
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

export async function apiRequest<T>(path: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers);
  headers.set('Accept', 'application/json');
  if (options.body && !(options.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json');
  }
  const token = getToken();
  if (token) headers.set('Authorization', `Bearer ${token}`);

  const method = (options.method ?? 'GET').toUpperCase();
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), FETCH_TIMEOUT_MS);

  let response: Response;
  try {
    response = await fetch(`${baseUrl()}${path}`, {
      ...options,
      headers,
      signal: options.signal ?? controller.signal,
    });
  } catch {
    if (canSnapshot(path, method)) {
      const cached = await readSnapshot<T>(path);
      if (cached !== null) return cached;
    }
    throw new ApiError('Impossible de joindre le serveur. Vérifiez votre connexion.', 0, 'NETWORK_ERROR');
  } finally {
    clearTimeout(timer);
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
    await cacheSnapshot(path, body);
  }

  return body as T;
}
