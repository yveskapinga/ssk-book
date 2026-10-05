import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { apiRequest, ApiError, setUnauthorizedHandler } from '@/src/lib/api';
import { registerForPush } from '@/src/lib/push';
import {
  clearSession,
  getStoredUser,
  getToken,
  hydrateSession,
  setSession,
  setStoredUser,
  type StoredUser,
} from '@/src/lib/storage';
import { useNetwork } from '@/src/lib/offline/status';
import { bootstrapOnlinePack } from '@/src/lib/offline/bootstrap';

type AuthValue = {
  user: StoredUser | null;
  ready: boolean;
  login: (email: string, password: string) => Promise<void>;
  register: (displayName: string, email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
};

const AuthContext = createContext<AuthValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const { online } = useNetwork();
  const [user, setUser] = useState<StoredUser | null>(null);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    setUnauthorizedHandler(() => setUser(null));
    return () => setUnauthorizedHandler(null);
  }, []);

  useEffect(() => {
    let cancelled = false;
    const failSafe = setTimeout(() => {
      if (!cancelled) setReady(true);
    }, 2500);
    void (async () => {
      try {
        await hydrateSession();
        const token = getToken();
        const cached = getStoredUser();
        if (!token) {
          if (!cancelled) {
            setUser(null);
            setReady(true);
          }
          return;
        }
        if (cached && !cancelled) setUser(cached);

        if (online) {
          try {
            const me = await apiRequest<{ data: { user: StoredUser } }>('/api/auth/me');
            await setStoredUser(me.data.user);
            if (!cancelled) setUser(me.data.user);
            void bootstrapOnlinePack();
            void registerForPush();
          } catch (err) {
            if (err instanceof ApiError && (err.status === 401 || err.status === 403)) {
              await clearSession();
              if (!cancelled) setUser(null);
            }
            // réseau : garder le snapshot local
          }
        }
      } finally {
        if (!cancelled) setReady(true);
      }
    })();
    return () => {
      cancelled = true;
      clearTimeout(failSafe);
    };
  }, [online]);

  const login = useCallback(async (email: string, password: string) => {
    const { data } = await apiRequest<{
      data: { token: string; user: StoredUser };
    }>('/api/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) });
    await setSession(data.token, data.user);
    setUser(data.user);
    void bootstrapOnlinePack();
    void registerForPush();
  }, []);

  const register = useCallback(async (displayName: string, email: string, password: string) => {
    const { data } = await apiRequest<{
      data: { token: string; user: StoredUser };
    }>('/api/auth/register', {
      method: 'POST',
      body: JSON.stringify({ displayName, email, password }),
    });
    await setSession(data.token, data.user);
    setUser(data.user);
    void bootstrapOnlinePack();
    void registerForPush();
  }, []);

  const logout = useCallback(async () => {
    try {
      await apiRequest('/api/auth/logout', { method: 'POST' });
    } catch {
      /* ignore */
    }
    await clearSession();
    setUser(null);
  }, []);

  const value = useMemo(
    () => ({ user, ready, login, register, logout }),
    [user, ready, login, register, logout],
  );
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider');
  return ctx;
}
