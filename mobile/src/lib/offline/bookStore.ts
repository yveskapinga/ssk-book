import * as SQLite from 'expo-sqlite';

const DB_NAME = 'ssk-book.db';

let dbPromise: Promise<SQLite.SQLiteDatabase> | null = null;

async function db(): Promise<SQLite.SQLiteDatabase> {
  if (!dbPromise) {
    dbPromise = (async () => {
      const instance = await SQLite.openDatabaseAsync(DB_NAME);
      await instance.execAsync(
        'CREATE TABLE IF NOT EXISTS kv (key TEXT PRIMARY KEY NOT NULL, value TEXT NOT NULL);',
      );
      return instance;
    })();
  }
  return dbPromise;
}

export async function putBookValue(key: string, value: unknown): Promise<void> {
  const instance = await db();
  await instance.runAsync('INSERT OR REPLACE INTO kv (key, value) VALUES (?, ?)', [
    key,
    JSON.stringify(value),
  ]);
}

export async function getBookValue<T>(key: string): Promise<T | null> {
  const instance = await db();
  const row = await instance.getFirstAsync<{ value: string }>('SELECT value FROM kv WHERE key = ?', [key]);
  if (!row?.value) return null;
  try {
    return JSON.parse(row.value) as T;
  } catch {
    return null;
  }
}
