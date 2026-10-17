import { useEffect, useState } from 'react';
import { ScrollView, Text } from 'react-native';
import { apiRequest } from '@/src/lib/api';
import { listPending, readSnapshot } from '@/src/lib/offline/db';
import { Card, Muted, Screen, Title } from '@/src/components/ui';
import { colors, fonts } from '@/src/lib/theme';

export default function ProgressScreen() {
  const [bookmarks, setBookmarks] = useState<unknown[]>([]);
  const [notes, setNotes] = useState<unknown[]>([]);
  const [pendingCount, setPendingCount] = useState(0);

  useEffect(() => {
    void (async () => {
      setPendingCount((await listPending()).length);
      try {
        const b = await apiRequest<{ data: { items: unknown[] } }>('/api/me/bookmarks');
        setBookmarks(b.data.items ?? []);
      } catch {
        const c = await readSnapshot<{ data: { items: unknown[] } }>('/api/me/bookmarks');
        setBookmarks(c?.data.items ?? []);
      }
      try {
        const n = await apiRequest<{ data: { items: unknown[] } }>('/api/me/notes');
        setNotes(n.data.items ?? []);
      } catch {
        const c = await readSnapshot<{ data: { items: unknown[] } }>('/api/me/notes');
        setNotes(c?.data.items ?? []);
      }
    })();
  }, []);

  return (
    <Screen>
      <ScrollView>
        <Title>Suivi</Title>
        <Muted>Favoris, notes et file de synchronisation.</Muted>
        {pendingCount > 0 ? (
          <Card>
            <Text style={{ fontFamily: fonts.uiBold, color: colors.ink }}>
              {pendingCount} action(s) en attente de sync
            </Text>
          </Card>
        ) : null}
        <Card>
          <Text style={{ fontFamily: fonts.uiBold, color: colors.ink, marginBottom: 6 }}>
            Favoris ({bookmarks.length})
          </Text>
          <Muted>{bookmarks.length ? 'Favoris synchronisés ou en cache.' : 'Aucun favori pour l’instant.'}</Muted>
        </Card>
        <Card>
          <Text style={{ fontFamily: fonts.uiBold, color: colors.ink, marginBottom: 6 }}>
            Notes ({notes.length})
          </Text>
          <Muted>{notes.length ? 'Notes synchronisées ou en cache.' : 'Aucune note pour l’instant.'}</Muted>
        </Card>
      </ScrollView>
    </Screen>
  );
}
