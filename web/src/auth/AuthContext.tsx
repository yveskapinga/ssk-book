import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { ApiError, apiRequest } from '../api/client'
import { useAppError } from '../lib/AppError'
import type { AuthPayload, User } from './types'

const TOKEN_KEY = 'ssk-book.access-token'

type AuthContextValue = {
  user: User | null
  token: string | null
  initializing: boolean
  login: (email: string, password: string) => Promise<void>
  register: (displayName: string, email: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const { report } = useAppError()
  const [token, setToken] = useState<string | null>(() => sessionStorage.getItem(TOKEN_KEY))
  const [user, setUser] = useState<User | null>(null)
  const [initializing, setInitializing] = useState(true)

  const establishSession = useCallback((payload: AuthPayload) => {
    sessionStorage.setItem(TOKEN_KEY, payload.token)
    setToken(payload.token)
    setUser(payload.user)
  }, [])

  useEffect(() => {
    if (!token) {
      setInitializing(false)
      return
    }
    apiRequest<{ data: { user: User } }>('/api/auth/me', {}, token)
      .then(({ data }) => setUser(data.user))
      .catch((err) => {
        sessionStorage.removeItem(TOKEN_KEY)
        setToken(null)
        setUser(null)
        if (!(err instanceof ApiError && (err.status === 401 || err.status === 403))) {
          report(err, 'La session n’a pas pu être restaurée.')
        }
      })
      .finally(() => setInitializing(false))
  }, [token, report])

  const login = useCallback(async (email: string, password: string) => {
    const { data } = await apiRequest<{ data: AuthPayload }>('/api/auth/login', {
      method: 'POST', body: JSON.stringify({ email, password }),
    })
    establishSession(data)
  }, [establishSession])

  const register = useCallback(async (displayName: string, email: string, password: string) => {
    const { data } = await apiRequest<{ data: AuthPayload }>('/api/auth/register', {
      method: 'POST', body: JSON.stringify({ displayName, email, password }),
    })
    establishSession(data)
  }, [establishSession])

  const logout = useCallback(async () => {
    try {
      if (token) await apiRequest('/api/auth/logout', { method: 'POST' }, token)
    } catch (err) {
      report(err, 'La déconnexion n’a pas pu être confirmée côté serveur.')
    } finally {
      sessionStorage.removeItem(TOKEN_KEY)
      setToken(null)
      setUser(null)
    }
  }, [token, report])

  const value = useMemo(() => ({ user, token, initializing, login, register, logout }), [user, token, initializing, login, register, logout])
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used inside AuthProvider')
  return context
}
