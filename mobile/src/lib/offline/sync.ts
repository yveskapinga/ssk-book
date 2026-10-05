import { apiRequest } from '@/src/lib/api';
import { listPending, removePending } from '@/src/lib/offline/db';

let flushing = false;

export async function flushPending(): Promise<void> {
  if (flushing) return;
  flushing = true;
  try {
    // Always re-read; remove by stable id so indices never shift mid-loop.
    for (;;) {
      const items = await listPending();
      if (items.length === 0) break;
      const item = items[0];
      try {
        await apiRequest(item.path, {
          method: item.method,
          body: item.body !== undefined ? JSON.stringify(item.body) : undefined,
        });
        await removePending(item.id);
      } catch {
        break;
      }
    }
  } finally {
    flushing = false;
  }
}
