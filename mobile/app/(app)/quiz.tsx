import { useEffect, useState } from 'react';
import { ScrollView, Text } from 'react-native';
import { apiRequest } from '@/src/lib/api';
import { useNetwork } from '@/src/lib/offline/status';
import { Card, Muted, OnlineGate, Screen, Title } from '@/src/components/ui';
import { colors, fonts } from '@/src/lib/theme';

type Quiz = { id: string; title: string; description?: string | null };

export default function QuizScreen() {
  const { online } = useNetwork();
  const [items, setItems] = useState<Quiz[]>([]);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!online) return;
    void (async () => {
      try {
        const res = await apiRequest<{ data: { items: Quiz[] } }>('/api/quizzes');
        setItems(res.data.items ?? []);
      } catch {
        setError('Impossible de charger les quiz.');
      }
    })();
  }, [online]);

  return (
    <Screen>
      <ScrollView>
        <Title>Quiz</Title>
        <Muted>Évaluez votre compréhension du livre.</Muted>
        {!online ? (
          <OnlineGate message="Les quiz nécessitent une connexion internet." />
        ) : error ? (
          <Muted>{error}</Muted>
        ) : items.length === 0 ? (
          <Muted>Aucun quiz publié pour le moment.</Muted>
        ) : (
          items.map((q) => (
            <Card key={q.id}>
              <Text style={{ fontFamily: fonts.uiBold, color: colors.ink, fontSize: 16 }}>{q.title}</Text>
              {q.description ? <Muted>{q.description}</Muted> : null}
            </Card>
          ))
        )}
      </ScrollView>
    </Screen>
  );
}
