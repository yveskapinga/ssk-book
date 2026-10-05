import { useEffect, useState } from 'react';
import { ScrollView, Text } from 'react-native';
import { apiRequest } from '@/src/lib/api';
import { useNetwork } from '@/src/lib/offline/status';
import { Button, Card, Field, Muted, OnlineGate, Screen, Title } from '@/src/components/ui';
import { colors, fonts } from '@/src/lib/theme';

export default function QuestionsScreen() {
  const { online } = useNetwork();
  const [slug, setSlug] = useState('');
  const [question, setQuestion] = useState('');
  const [answer, setAnswer] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!online) return;
    void apiRequest<{ data: { items: { slug: string }[] } }>('/api/library')
      .then((r) => setSlug(r.data.items[0]?.slug ?? ''))
      .catch(() => setError('Bibliothèque indisponible.'));
  }, [online]);

  return (
    <Screen>
      <ScrollView>
        <Title>Demander au livre</Title>
        <Muted>Réponses fondées sur les passages publiés (Gemini côté serveur).</Muted>
        {!online ? (
          <OnlineGate message="Posez vos questions lorsque vous êtes en ligne." />
        ) : (
          <>
            <Field
              placeholder="Votre question…"
              value={question}
              onChangeText={setQuestion}
              multiline
              style={{ minHeight: 100, textAlignVertical: 'top' }}
            />
            {error ? <Text style={{ color: colors.danger, fontFamily: fonts.ui }}>{error}</Text> : null}
            <Button
              label="Envoyer"
              busy={busy}
              disabled={!slug || !question.trim()}
              onPress={() => {
                setBusy(true);
                setError('');
                setAnswer('');
                void apiRequest<{ data: { answer?: string; content?: string } }>(
                  `/api/books/${slug}/questions`,
                  { method: 'POST', body: JSON.stringify({ question: question.trim() }) },
                )
                  .then((r) => setAnswer(r.data.answer ?? r.data.content ?? JSON.stringify(r.data)))
                  .catch((e) => setError(e instanceof Error ? e.message : 'Échec'))
                  .finally(() => setBusy(false));
              }}
            />
            {answer ? (
              <Card>
                <Text style={{ fontFamily: fonts.uiBold, color: colors.ink, marginBottom: 8 }}>Réponse</Text>
                <Text style={{ fontFamily: fonts.display, fontSize: 16, lineHeight: 26, color: colors.inkSoft }}>
                  {answer}
                </Text>
              </Card>
            ) : null}
          </>
        )}
      </ScrollView>
    </Screen>
  );
}
