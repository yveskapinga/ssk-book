import { useCallback, useState } from 'react';
import { ScrollView, Text, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import { apiRequest } from '@/src/lib/api';
import { useAuth } from '@/src/lib/auth';
import { readSnapshot } from '@/src/lib/offline/db';
import { Button, Card, Muted, Screen, Title } from '@/src/components/ui';
import { colors, fonts } from '@/src/lib/theme';

type Dash = {
  stats?: {
    reading_percent?: number | string;
    bookmark_count?: number | string;
    note_count?: number | string;
    quiz_count?: number | string;
    question_count?: number | string;
  };
  library?: { slug: string; title: string; page_count?: number | null }[];
};

export default function HomeScreen() {
  const { user } = useAuth();
  const [dash, setDash] = useState<Dash | null>(null);

  useFocusEffect(
    useCallback(() => {
      let active = true;
      void (async () => {
        try {
          const res = await apiRequest<{ data: Dash }>('/api/me/dashboard');
          if (active) setDash(res.data);
        } catch {
          const cached = await readSnapshot<{ data: Dash }>('/api/me/dashboard');
          if (active && cached?.data) setDash(cached.data);
        }
      })();
      return () => {
        active = false;
      };
    }, []),
  );

  const pct = Math.round(Number(dash?.stats?.reading_percent ?? 0));
  const book = dash?.library?.[0];

  return (
    <Screen>
      <ScrollView contentContainerStyle={{ paddingBottom: 24 }}>
        <Title>Bonjour, {user?.displayName?.split(' ')[0] ?? 'lecteur'}</Title>
        <Muted>Lecture interactive du livre du SSK.</Muted>

        <View
          style={{
            backgroundColor: colors.sidebar,
            borderRadius: 16,
            padding: 20,
            marginBottom: 14,
          }}
        >
          <Text style={{ color: colors.sidebarMuted, fontFamily: fonts.uiBold, fontSize: 12, letterSpacing: 1 }}>
            REPRENDRE
          </Text>
          <Text style={{ color: '#fff', fontFamily: fonts.displayBold, fontSize: 22, lineHeight: 28, marginVertical: 8 }}>
            {book?.title ?? 'SSK Book'}
          </Text>
          <View style={{ height: 7, borderRadius: 99, backgroundColor: 'rgba(255,255,255,0.16)', overflow: 'hidden' }}>
            <View style={{ width: `${Math.min(100, pct)}%`, height: '100%', backgroundColor: colors.gold }} />
          </View>
          <Text style={{ color: '#d7e4f7', marginTop: 8, fontFamily: fonts.ui }}>{pct}% parcouru</Text>
          <Button label="Continuer la lecture" onPress={() => router.push('/reading')} />
        </View>

        <Card>
          <Text style={{ fontFamily: fonts.uiBold, color: colors.ink, marginBottom: 6 }}>Raccourcis</Text>
          <Button label="Quiz" variant="secondary" onPress={() => router.push('/quiz')} />
          <Button label="Demander au livre" variant="ghost" onPress={() => router.push('/questions')} />
          <Button label="Suivi" variant="ghost" onPress={() => router.push('/progress')} />
        </Card>
      </ScrollView>
    </Screen>
  );
}
