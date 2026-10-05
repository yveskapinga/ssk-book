import { apiRequest } from '@/src/lib/api';
import { listPending, removePending } from '@/src/lib/offline/db';

let flushing = false;

export async function flushPending(): Promise<void> {
  if (flushing) return;
  flushing = true;
  try {
    const items = await listPending();
    for (const item of items) {
      try {
        await apiRequest(item.op.path, {
          method: item.op.method,
          body: item.op.body !== undefined ? JSON.stringify(item.op.body) : undefined,
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
