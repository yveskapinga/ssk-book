import { useCallback, useEffect, useState } from 'react';
import { Modal, Pressable, ScrollView, Text, View } from 'react-native';
import { apiRequest } from '@/src/lib/api';
import { enqueuePending, readSnapshot } from '@/src/lib/offline/db';
import {
  advanceLocal,
  downloadOfflinePack,
  firstPassageAtPage,
  presentFromPack,
  readLocalReadingState,
  readOfflinePack,
  setLocalCursor,
  type LocalReadingState,
  type OfflinePack,
  type PresentedReading,
} from '@/src/lib/offline/pack';
import { useNetwork } from '@/src/lib/offline/status';
import { Button, Card, Muted, Screen, Title } from '@/src/components/ui';
import { colors, fonts } from '@/src/lib/theme';

export default function ReadingScreen() {
  const { online } = useNetwork();
  const [slug, setSlug] = useState('');
  const [pack, setPack] = useState<OfflinePack | null>(null);
  const [state, setState] = useState<LocalReadingState | null>(null);
  const [reading, setReading] = useState<PresentedReading | null>(null);
  const [tocOpen, setTocOpen] = useState(false);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [downloading, setDownloading] = useState(false);
  const [bookmarkBusy, setBookmarkBusy] = useState(false);
  const [note, setNote] = useState('');

  const applyPack = useCallback((nextPack: OfflinePack, nextState: LocalReadingState, passageId?: string | null) => {
    setPack(nextPack);
    setState(nextState);
    setReading(presentFromPack(nextPack, nextState, passageId));
    setSlug(nextPack.slug);
  }, []);

  const ensurePack = useCallback(
    async (bookSlug: string, forceDownload = false) => {
      let local = forceDownload ? null : await readOfflinePack(bookSlug);
      if (!local && online) {
        setDownloading(true);
        try {
          local = await downloadOfflinePack(bookSlug);
        } finally {
          setDownloading(false);
        }
      }
      if (!local) {
        setError('Téléchargez le livre une fois en ligne pour lire hors connexion.');
        return null;
      }
      const localState =
        (await readLocalReadingState(bookSlug)) ??
        ({
          cursorId: local.frontierPassageId,
          frontierPosition: local.frontierPosition,
          allRead: local.allRead,
        } satisfies LocalReadingState);
      applyPack(local, localState, localState.cursorId);
      setError('');
      return { pack: local, state: localState };
    },
    [applyPack, online],
  );

  const loadReading = useCallback(async () => {
    try {
      let bookSlug = '';
      if (online) {
        const lib = await apiRequest<{ data: { items: { slug: string }[] } }>('/api/library');
        bookSlug = lib.data?.items?.[0]?.slug ?? '';
      } else {
        const lib = await readSnapshot<{ data: { items: { slug: string }[] } }>('/api/library');
        bookSlug = lib?.data?.items?.[0]?.slug ?? '';
      }
      if (!bookSlug) {
        setError('Aucun livre publié.');
        return;
      }
      await ensurePack(bookSlug);
    } catch (err) {
      const message = err instanceof Error ? err.message : '';
      setError(message || 'Impossible de préparer la lecture.');
    }
  }, [ensurePack, online]);

  useEffect(() => {
    void loadReading();
  }, [loadReading]);

  const goToPassage = async (passageId: string) => {
    if (!pack || !state) return;
    const result = await setLocalCursor(pack, state, passageId);
    if (!result.reading?.unlocked) {
      setError('Ce passage n’est pas encore déverrouillé.');
      return;
    }
    setError('');
    setState(result.state);
    setReading(result.reading);
  };

  const markProgress = async (displayedMs: number, advance: boolean) => {
    if (!slug || !pack || !state || !reading) return;
    if (!reading.unlocked) {
      setError('Ce passage n’est pas encore déverrouillé.');
      return;
    }
    const body = {
      passageId: reading.passage.id,
      displayedMs,
      advance,
    };
    const path = `/api/books/${slug}/reading/progress`;
    setBusy(true);
    try {
      if (online) {
        await apiRequest(path, { method: 'POST', body: JSON.stringify(body) });
        const refreshed = await downloadOfflinePack(slug, { preserveCursor: !advance });
        const localState = (await readLocalReadingState(slug))!;
        applyPack(refreshed, localState, localState.cursorId);
      } else {
        await enqueuePending({ method: 'POST', path, body });
        if (advance) {
          const result = await advanceLocal(pack, state, reading.passage.id);
          setState(result.state);
          setReading(result.reading);
        }
      }
      setError('');
    } catch {
      await enqueuePending({ method: 'POST', path, body });
      if (advance) {
        const result = await advanceLocal(pack, state, reading.passage.id);
        setState(result.state);
        setReading(result.reading);
      }
    } finally {
      setBusy(false);
    }
  };

  const addBookmark = async () => {
    if (!slug || !reading?.passage.chunk_id) {
      setError('Favori indisponible pour ce passage.');
      return;
    }
    const path = `/api/books/${slug}/bookmarks`;
    const body = { chunkId: reading.passage.chunk_id };
    setBookmarkBusy(true);
    try {
      if (online) await apiRequest(path, { method: 'POST', body: JSON.stringify(body) });
      else await enqueuePending({ method: 'POST', path, body });
      setNote('Favori enregistré (sync à la reconnexion si hors ligne).');
    } catch {
      await enqueuePending({ method: 'POST', path, body });
      setNote('Favori mis en file hors ligne.');
    } finally {
      setBookmarkBusy(false);
    }
  };

  return (
    <Screen style={{ padding: 0 }}>
      <View
        style={{
          paddingHorizontal: 16,
          paddingTop: 16,
          flexDirection: 'row',
          justifyContent: 'space-between',
          alignItems: 'center',
          gap: 12,
        }}
      >
        <Title>Lecture</Title>
        <View style={{ flexDirection: 'row', gap: 14, alignItems: 'center' }}>
          {online ? (
            <Pressable
              onPress={() => slug && void ensurePack(slug, true)}
              disabled={downloading}
            >
              <Text style={{ color: colors.accent, fontFamily: fonts.uiBold }}>
                {downloading ? 'Téléchargement…' : 'Resync'}
              </Text>
            </Pressable>
          ) : null}
          <Pressable onPress={() => setTocOpen(true)}>
            <Text style={{ color: colors.accent, fontFamily: fonts.uiBold }}>Sommaire</Text>
          </Pressable>
        </View>
      </View>

      {pack ? (
        <View style={{ paddingHorizontal: 16 }}>
          <Muted>
            {pack.title} · {pack.passageCount} passages
            {pack.downloadedAt ? ' · pack local prêt' : ''}
            {!online ? ' · hors ligne' : ''}
          </Muted>
        </View>
      ) : null}

      {error ? (
        <View style={{ paddingHorizontal: 16, gap: 8 }}>
          <Muted>{error}</Muted>
          <Button label="Réessayer" variant="secondary" onPress={() => void loadReading()} />
        </View>
      ) : null}
      {note ? (
        <View style={{ paddingHorizontal: 16 }}>
          <Text style={{ color: colors.accent, fontFamily: fonts.ui, marginBottom: 8 }}>{note}</Text>
        </View>
      ) : null}

      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }}>
        {downloading && !reading ? <Muted>Téléchargement du livre pour le mode hors ligne…</Muted> : null}
        {reading ? (
          <Card>
            <Text style={{ color: colors.accent, fontFamily: fonts.uiBold, fontSize: 12, letterSpacing: 1 }}>
              PAGE {reading.passage.page_number} · {reading.passage.position + 1}/{reading.total}
              {!reading.unlocked ? ' · VERROUILLÉ' : ''}
            </Text>
            {reading.passage.chapter_title ? (
              <Text style={{ fontFamily: fonts.displayBold, fontSize: 22, lineHeight: 28, color: colors.ink, marginVertical: 10 }}>
                {reading.passage.chapter_title}
              </Text>
            ) : null}
            <Text style={{ fontFamily: fonts.display, fontSize: 18, lineHeight: 32, color: colors.inkSoft }}>
              {reading.passage.kind === 'TEXT'
                ? reading.passage.body
                : '[Image — reconnectez-vous pour afficher la figure]'}
            </Text>
          </Card>
        ) : !error && !downloading ? (
          <Muted>Chargement du passage…</Muted>
        ) : null}

        <View style={{ flexDirection: 'row', gap: 10 }}>
          <View style={{ flex: 1 }}>
            <Button
              label="Précédent"
              variant="secondary"
              disabled={!reading?.previousId || busy}
              onPress={() => reading?.previousId && void goToPassage(reading.previousId)}
            />
          </View>
          <View style={{ flex: 1 }}>
            <Button
              label="Suivant"
              busy={busy}
              disabled={!reading || busy || (!reading.nextId && !reading.canAdvance)}
              onPress={() => void markProgress(8000, true)}
            />
          </View>
        </View>
        <Button
          label="Ajouter aux favoris"
          variant="ghost"
          busy={bookmarkBusy}
          disabled={!reading?.passage.chunk_id || bookmarkBusy}
          onPress={() => void addBookmark()}
        />
      </ScrollView>

      <Modal visible={tocOpen} animationType="slide" transparent onRequestClose={() => setTocOpen(false)}>
        <Pressable style={{ flex: 1, backgroundColor: 'rgba(10,39,68,0.35)' }} onPress={() => setTocOpen(false)} />
        <View
          style={{
            maxHeight: '70%',
            backgroundColor: colors.panel,
            borderTopLeftRadius: 16,
            borderTopRightRadius: 16,
            padding: 16,
          }}
        >
          <Text style={{ fontFamily: fonts.displayBold, fontSize: 22, lineHeight: 28, color: colors.ink, marginBottom: 12 }}>
            Sommaire
          </Text>
          <ScrollView>
            {(pack?.toc ?? []).map((n) => {
              const target = pack ? firstPassageAtPage(pack, n.start_page) : null;
              const locked =
                !!pack &&
                !!state &&
                !!target &&
                !state.allRead &&
                target.position > state.frontierPosition;
              return (
                <Pressable
                  key={n.id}
                  onPress={() => {
                    setTocOpen(false);
                    if (!target) return;
                    void goToPassage(target.id);
                  }}
                  style={{ paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: colors.line, opacity: locked ? 0.45 : 1 }}
                >
                  <Text style={{ fontFamily: fonts.uiBold, color: colors.ink }}>{n.title}</Text>
                  <Text style={{ color: colors.muted, fontSize: 12 }}>
                    p. {n.start_page}
                    {locked ? ' · verrouillé' : ''}
                  </Text>
                </Pressable>
              );
            })}
          </ScrollView>
        </View>
      </Modal>
    </Screen>
  );
}
