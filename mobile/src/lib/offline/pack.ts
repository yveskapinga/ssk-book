import { apiRequest, LONG_REQUEST_TIMEOUT_MS } from '@/src/lib/api';
import { cacheBook, cacheSnapshot, readBook } from '@/src/lib/offline/db';

export type OfflineToc = { id: string; title: string; start_page: number };

export type OfflinePassage = {
  id: string;
  position: number;
  page_number: number;
  kind: 'TEXT' | 'IMAGE';
  body: string;
  chapter_title: string | null;
  chunk_id: string | null;
  figure_id: string | null;
};

export type OfflinePack = {
  slug: string;
  title: string;
  versionId: string;
  toc: OfflineToc[];
  frontierPassageId: string;
  frontierPosition: number;
  allRead: boolean;
  passageCount: number;
  passages: OfflinePassage[];
  downloadedAt: number;
};

export type LocalReadingState = {
  cursorId: string;
  frontierPosition: number;
  allRead: boolean;
};

export type PresentedReading = {
  passage: {
    id: string;
    position: number;
    page_number: number;
    kind: 'TEXT' | 'IMAGE';
    body: string;
    chapter_title: string | null;
    chunk_id: string | null;
    figure_id: string | null;
  };
  status: string;
  total: number;
  previousId: string | null;
  nextId: string | null;
  canAdvance: boolean;
  unlocked: boolean;
};

function packKey(slug: string): string {
  return `pack:${slug}`;
}

function stateKey(slug: string): string {
  return `packState:${slug}`;
}

export async function saveOfflinePack(
  pack: OfflinePack,
  options?: { preserveCursor?: boolean },
): Promise<void> {
  await cacheBook(packKey(pack.slug), pack);
  const previous = options?.preserveCursor ? await readLocalReadingState(pack.slug) : null;
  const cursorStillValid =
    !!previous?.cursorId && pack.passages.some((p) => p.id === previous.cursorId);
  await cacheBook(stateKey(pack.slug), {
    cursorId: cursorStillValid ? previous!.cursorId : pack.frontierPassageId,
    frontierPosition: pack.frontierPosition,
    allRead: pack.allRead,
  } satisfies LocalReadingState);
}

export async function readOfflinePack(slug: string): Promise<OfflinePack | null> {
  return readBook<OfflinePack>(packKey(slug));
}

export async function readLocalReadingState(slug: string): Promise<LocalReadingState | null> {
  return readBook<LocalReadingState>(stateKey(slug));
}

export async function saveLocalReadingState(slug: string, state: LocalReadingState): Promise<void> {
  await cacheBook(stateKey(slug), state);
}

export async function downloadOfflinePack(
  slug: string,
  options?: { preserveCursor?: boolean },
): Promise<OfflinePack> {
  const res = await apiRequest<{ data: Omit<OfflinePack, 'downloadedAt'> }>(
    `/api/books/${slug}/reading/pack`,
    { timeoutMs: LONG_REQUEST_TIMEOUT_MS },
  );
  if (!res.data?.passages) {
    throw new Error('Le pack de lecture est incomplet (aucun passage).');
  }
  const pack: OfflinePack = { ...res.data, downloadedAt: Date.now() };
  try {
    await saveOfflinePack(pack, options);
    await cacheSnapshot(`/api/books/${slug}`, {
      data: {
        book: { slug: pack.slug, title: pack.title, version_id: pack.versionId },
        toc: pack.toc,
        passageCount: pack.passageCount,
      },
    });
  } catch {
    /* Keep the pack in memory even if disk persist fails. */
  }
  return pack;
}

export function presentFromPack(
  pack: OfflinePack,
  state: LocalReadingState,
  passageId?: string | null,
): PresentedReading | null {
  if (!pack.passages.length) return null;
  const byId = new Map(pack.passages.map((p) => [p.id, p]));
  const targetId = passageId && byId.has(passageId) ? passageId : state.cursorId;
  const passage = byId.get(targetId) ?? pack.passages[0];
  const index = pack.passages.findIndex((p) => p.id === passage.id);
  const prev = index > 0 ? pack.passages[index - 1] : null;
  const next = index >= 0 && index < pack.passages.length - 1 ? pack.passages[index + 1] : null;
  const unlocked = state.allRead || passage.position <= state.frontierPosition;

  return {
    passage: {
      id: passage.id,
      position: passage.position,
      page_number: passage.page_number,
      kind: passage.kind,
      body: passage.body,
      chapter_title: passage.chapter_title,
      chunk_id: passage.chunk_id,
      figure_id: passage.figure_id,
    },
    status: unlocked ? 'IN_PROGRESS' : 'LOCKED',
    total: pack.passages.length,
    previousId: prev?.id ?? null,
    nextId: next?.id ?? null,
    canAdvance: !!next && (state.allRead || (next.position <= state.frontierPosition + 1 && unlocked)),
    unlocked,
  };
}

export function firstPassageAtPage(pack: OfflinePack, page: number): OfflinePassage | null {
  return pack.passages.find((p) => p.page_number === page) ?? null;
}

/** Local advance: mark current unlocked, bump frontier when sequential, move cursor. */
export async function advanceLocal(
  pack: OfflinePack,
  state: LocalReadingState,
  currentId: string,
): Promise<{ state: LocalReadingState; reading: PresentedReading | null }> {
  const current = pack.passages.find((p) => p.id === currentId);
  if (!current) return { state, reading: presentFromPack(pack, state, currentId) };

  let frontierPosition = state.frontierPosition;
  let allRead = state.allRead;
  if (!allRead && current.position === frontierPosition) {
    const next = pack.passages.find((p) => p.position === frontierPosition + 1);
    if (next) frontierPosition = next.position;
    else allRead = true;
  }

  const nextPassage =
    pack.passages.find((p) => p.position === current.position + 1) ??
    pack.passages.find((p) => p.id === currentId) ??
    pack.passages[0];

  const nextState: LocalReadingState = {
    cursorId: nextPassage.id,
    frontierPosition,
    allRead,
  };
  await saveLocalReadingState(pack.slug, nextState);
  return { state: nextState, reading: presentFromPack(pack, nextState, nextState.cursorId) };
}

export async function setLocalCursor(
  pack: OfflinePack,
  state: LocalReadingState,
  passageId: string,
): Promise<{ state: LocalReadingState; reading: PresentedReading | null }> {
  const passage = pack.passages.find((p) => p.id === passageId);
  if (!passage) return { state, reading: presentFromPack(pack, state) };
  if (!state.allRead && passage.position > state.frontierPosition) {
    return { state, reading: presentFromPack(pack, state, state.cursorId) };
  }
  const nextState = { ...state, cursorId: passageId };
  await saveLocalReadingState(pack.slug, nextState);
  return { state: nextState, reading: presentFromPack(pack, nextState, passageId) };
}
