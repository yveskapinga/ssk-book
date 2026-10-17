import AsyncStorage from '@react-native-async-storage/async-storage';

const PREFIX = 'ssk.kv.';
const PENDING_KEY = 'ssk.pending';

type PendingOp = {
  method: string;
  path: string;
  body?: unknown;
};

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
  await put('book', key, value);
}

export async function readBook<T>(key: string): Promise<T | null> {
  return get<T>('book', key);
}

export async function enqueuePending(op: PendingOp): Promise<void> {
  const raw = (await AsyncStorage.getItem(PENDING_KEY)) ?? '[]';
  const list = JSON.parse(raw) as PendingOp[];
  list.push(op);
  await AsyncStorage.setItem(PENDING_KEY, JSON.stringify(list));
}

export async function listPending(): Promise<{ id: number; op: PendingOp }[]> {
  const raw = (await AsyncStorage.getItem(PENDING_KEY)) ?? '[]';
  const list = JSON.parse(raw) as PendingOp[];
  return list.map((op, id) => ({ id, op }));
}

export async function removePending(id: number): Promise<void> {
  const raw = (await AsyncStorage.getItem(PENDING_KEY)) ?? '[]';
  const list = JSON.parse(raw) as PendingOp[];
  list.splice(id, 1);
  await AsyncStorage.setItem(PENDING_KEY, JSON.stringify(list));
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
