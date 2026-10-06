import AsyncStorage from '@react-native-async-storage/async-storage';
import { getBookValue, putBookValue } from '@/src/lib/offline/bookStore';

const PREFIX = 'ssk.kv.';
const PENDING_KEY = 'ssk.pending';

export type PendingOp = {
  id: string;
  method: string;
  path: string;
  body?: unknown;
  createdAt: number;
};

function newId(): string {
  return `p_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 10)}`;
}

async function put(store: string, key: string, value: unknown): Promise<void> {
  await AsyncStorage.setItem(`${PREFIX}${store}:${key}`, JSON.stringify(value));
}

async function get<T>(store: string, key: string): Promise<T | null> {
  const raw = await AsyncStorage.getItem(`${PREFIX}${store}:${key}`);
  if (!raw) return null;
  try {
    return JSON.parse(raw) as T;
  } catch {
    return null;
  }
}

export async function cacheSnapshot(key: string, value: unknown): Promise<void> {
  await put('snapshots', key, { at: Date.now(), value });
}

export async function readSnapshot<T>(key: string): Promise<T | null> {
  const row = await get<{ at: number; value: T }>('snapshots', key);
  return row?.value ?? null;
}

export async function cacheBook(key: string, value: unknown): Promise<void> {
  await putBookValue(`book:${key}`, value);
}

export async function readBook<T>(key: string): Promise<T | null> {
  const fromSqlite = await getBookValue<T>(`book:${key}`);
  if (fromSqlite !== null) return fromSqlite;
  return get<T>('book', key);
}

export async function enqueuePending(op: Omit<PendingOp, 'id' | 'createdAt'>): Promise<void> {
  const raw = (await AsyncStorage.getItem(PENDING_KEY)) ?? '[]';
  const list = JSON.parse(raw) as PendingOp[];
  list.push({
    id: newId(),
    method: op.method,
    path: op.path,
    body: op.body,
    createdAt: Date.now(),
  });
  await AsyncStorage.setItem(PENDING_KEY, JSON.stringify(list));
}

export async function listPending(): Promise<PendingOp[]> {
  const raw = (await AsyncStorage.getItem(PENDING_KEY)) ?? '[]';
  const list = JSON.parse(raw) as Array<PendingOp | { method: string; path: string; body?: unknown }>;
  // migrate legacy entries without id
  return list.map((item, index) => {
    if ('id' in item && typeof item.id === 'string') return item as PendingOp;
    return {
      id: `legacy_${index}`,
      method: item.method,
      path: item.path,
      body: item.body,
      createdAt: 0,
    };
  });
}

export async function removePending(id: string): Promise<void> {
  const list = await listPending();
  await AsyncStorage.setItem(PENDING_KEY, JSON.stringify(list.filter((item) => item.id !== id)));
}

export async function appendLocalNotification(item: {
  id: string;
  title: string;
  body: string;
  at: number;
  data?: Record<string, unknown>;
}): Promise<void> {
  const prev = (await get<typeof item[]>('prefs', 'notifications')) ?? [];
  await put('prefs', 'notifications', [item, ...prev].slice(0, 100));
}

export async function listLocalNotifications(): Promise<
  { id: string; title: string; body: string; at: number; data?: Record<string, unknown> }[]
> {
  return (await get('prefs', 'notifications')) ?? [];
}
