import { apiRequest } from '@/src/lib/api';
import { cacheSnapshot } from '@/src/lib/offline/db';
import { downloadOfflinePack } from '@/src/lib/offline/pack';

type LibraryItem = { slug: string; title: string };

export async function bootstrapOnlinePack(): Promise<void> {
  const library = await apiRequest<{ data: { items: LibraryItem[] } }>('/api/library');
  await cacheSnapshot('/api/library', library);
  const slug = library.data.items[0]?.slug;
  if (!slug) return;

  // Full offline reading pack (TOC + all passages + frontier).
  await downloadOfflinePack(slug);

  try {
    const dash = await apiRequest<{ data: unknown }>('/api/me/dashboard');
    await cacheSnapshot('/api/me/dashboard', dash);
  } catch {
    /* optional */
  }

  try {
    const bookmarks = await apiRequest<{ data: unknown }>('/api/me/bookmarks');
    await cacheSnapshot('/api/me/bookmarks', bookmarks);
  } catch {
    /* optional */
  }

  try {
    const notes = await apiRequest<{ data: unknown }>('/api/me/notes');
    await cacheSnapshot('/api/me/notes', notes);
  } catch {
    /* optional */
  }
}
