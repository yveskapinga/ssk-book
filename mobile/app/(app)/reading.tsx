import { useCallback, useEffect, useState } from 'react';
import { Modal, Pressable, ScrollView, Text, View } from 'react-native';
import { apiRequest } from '@/src/lib/api';
import { cacheBook, enqueuePending, readBook, readSnapshot } from '@/src/lib/offline/db';
import { useNetwork } from '@/src/lib/offline/status';
import { Button, Card, Muted, Screen, Title } from '@/src/components/ui';
import { colors, fonts } from '@/src/lib/theme';

type Toc = { id: string; title: string; start_page: number };
type Reading = {
  passage: {
    id: string;
    position: number;
    page_number: number;
    kind: 'TEXT' | 'IMAGE';
    body: string;
    chapter_title: string | null;
  };
  status: string;
  total: number;
  previousId: string | null;
  nextId: string | null;
  canAdvance: boolean;
};

export default function ReadingScreen() {
  const { online } = useNetwork();
  const [slug, setSlug] = useState('');
  const [toc, setToc] = useState<Toc[]>([]);
  const [reading, setReading] = useState<Reading | null>(null);
  const [tocOpen, setTocOpen] = useState(false);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const loadReading = useCallback(async (bookSlug: string, passageId?: string | null) => {
    const suffix = passageId ? `?passage=${encodeURIComponent(passageId)}` : '';
    const path = `/api/books/${bookSlug}/reading${suffix}`;
    try {
      const res = await apiRequest<{ data: Reading }>(path);
      setReading(res.data);
      await cacheBook(`reading:${bookSlug}`, res.data);
      setError('');
    } catch {
      const cached = await readBook<Reading>(`reading:${bookSlug}`);
      if (cached) {
        setReading(cached);
        setError('');
      } else {
        setError('Contenu de lecture indisponible hors ligne.');
      }
    }
  }, []);

  useEffect(() => {
    void (async () => {
      try {
        const lib = await apiRequest<{ data: { items: { slug: string; title: string }[] } }>('/api/library');
        const s = lib.data.items[0]?.slug;
        if (!s) {
          setError('Aucun livre publié.');
          return;
        }
        setSlug(s);
        const book = await apiRequest<{ data: { toc: Toc[] } }>(`/api/books/${s}`);
        setToc(book.data.toc ?? []);
        await cacheBook(`meta:${s}`, book.data);
        await loadReading(s);
      } catch {
        const lib = await readSnapshot<{ data: { items: { slug: string }[] } }>('/api/library');
        const s = lib?.data.items[0]?.slug;
        if (!s) {
          setError('Bootstrapz une fois en ligne pour lire hors connexion.');
          return;
        }
        setSlug(s);
        const meta = await readBook<{ toc: Toc[] }>(`meta:${s}`);
        if (meta?.toc) setToc(meta.toc);
        await loadReading(s);
      }
    })();
  }, [loadReading]);

  const markProgress = async (displayedMs: number, advance: boolean) => {
    if (!slug || !reading) return;
    const body = {
      passageId: reading.passage.id,
      displayedMs,
      advance,
    };
    const path = `/api/books/${slug}/reading/progress`;
    setBusy(true);
    try {
      if (online) {
        const res = await apiRequest<{ data: Reading }>(path, {
          method: 'POST',
          body: JSON.stringify(body),
        });
        setReading(res.data);
        await cacheBook(`reading:${slug}`, res.data);
      } else {
        await enqueuePending({ method: 'POST', path, body });
        if (advance && reading.nextId) await loadReading(slug, reading.nextId);
      }
    } catch {
      await enqueuePending({ method: 'POST', path, body });
      if (advance && reading.nextId) await loadReading(slug, reading.nextId);
    } finally {
      setBusy(false);
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
        }}
      >
        <Title>Lecture</Title>
        <Pressable onPress={() => setTocOpen(true)}>
          <Text style={{ color: colors.accent, fontFamily: fonts.uiBold }}>Sommaire</Text>
        </Pressable>
      </View>
      {error ? (
        <View style={{ paddingHorizontal: 16 }}>
          <Muted>{error}</Muted>
        </View>
      ) : null}
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }}>
        {reading ? (
          <Card>
            <Text style={{ color: colors.accent, fontFamily: fonts.uiBold, fontSize: 12, letterSpacing: 1 }}>
              PAGE {reading.passage.page_number} · {reading.passage.position + 1}/{reading.total}
            </Text>
            {reading.passage.chapter_title ? (
              <Text style={{ fontFamily: fonts.displayBold, fontSize: 22, color: colors.ink, marginVertical: 10 }}>
                {reading.passage.chapter_title}
              </Text>
            ) : null}
            <Text style={{ fontFamily: fonts.display, fontSize: 18, lineHeight: 32, color: colors.inkSoft }}>
              {reading.passage.kind === 'TEXT'
                ? reading.passage.body
                : '[Image — ouvrez en ligne pour l’afficher]'}
            </Text>
          </Card>
        ) : !error ? (
          <Muted>Chargement du passage…</Muted>
        ) : null}
        <View style={{ flexDirection: 'row', gap: 10 }}>
          <View style={{ flex: 1 }}>
            <Button
              label="Précédent"
              variant="secondary"
              disabled={!reading?.previousId || busy}
              onPress={() => reading?.previousId && void loadReading(slug, reading.previousId)}
            />
          </View>
          <View style={{ flex: 1 }}>
            <Button
              label="Suivant"
              busy={busy}
              disabled={!reading || busy}
              onPress={() => void markProgress(8000, true)}
            />
          </View>
        </View>
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
          <Text style={{ fontFamily: fonts.displayBold, fontSize: 22, color: colors.ink, marginBottom: 12 }}>
            Sommaire
          </Text>
          <ScrollView>
            {toc.map((n) => (
              <Pressable
                key={n.id}
                onPress={() => {
                  setTocOpen(false);
                  void (async () => {
                    try {
                      const res = await apiRequest<{ data: Reading }>(
                        `/api/books/${slug}/reading?page=${n.start_page}`,
                      );
                      setReading(res.data);
                      await cacheBook(`reading:${slug}`, res.data);
                    } catch {
                      /* keep current */
                    }
                  })();
                }}
                style={{ paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: colors.line }}
              >
                <Text style={{ fontFamily: fonts.uiBold, color: colors.ink }}>{n.title}</Text>
                <Text style={{ color: colors.muted, fontSize: 12 }}>p. {n.start_page}</Text>
              </Pressable>
            ))}
          </ScrollView>
        </View>
      </Modal>
    </Screen>
  );
}
