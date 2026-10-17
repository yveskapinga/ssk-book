import { apiRequest } from '@/src/lib/api';
import { cacheBook, cacheSnapshot } from '@/src/lib/offline/db';

type LibraryItem = { slug: string; title: string };

export async function bootstrapOnlinePack(): Promise<void> {
  const library = await apiRequest<{ data: { items: LibraryItem[] } }>('/api/library');
  await cacheSnapshot('/api/library', library);
  const slug = library.data.items[0]?.slug;
  if (!slug) return;

  const book = await apiRequest<{ data: unknown }>(`/api/books/${slug}`);
  await cacheBook(`meta:${slug}`, book.data);
  await cacheSnapshot(`/api/books/${slug}`, book);

  const reading = await apiRequest<{ data: unknown }>(`/api/books/${slug}/reading`);
  await cacheBook(`reading:${slug}`, reading.data);
  await cacheSnapshot(`/api/books/${slug}/reading`, reading);

  try {
    const dash = await apiRequest<{ data: unknown }>('/api/me/dashboard');
    await cacheSnapshot('/api/me/dashboard', dash);
  } catch {
    /* optional */
  }
}
