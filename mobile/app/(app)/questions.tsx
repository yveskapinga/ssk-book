import { useEffect, useState } from 'react';
import { ActivityIndicator, ScrollView, Text, View } from 'react-native';
import { apiRequest, ApiError, LONG_REQUEST_TIMEOUT_MS } from '@/src/lib/api';
import { useNetwork } from '@/src/lib/offline/status';
import { Button, Card, Field, Muted, OnlineGate, Screen, Title } from '@/src/components/ui';
import { colors, fonts } from '@/src/lib/theme';

type Source = {
  id: string;
  start_page?: number;
  end_page?: number;
  content?: string;
};

type AskResult = {
  conversationId: string;
  answer: string;
  insufficientEvidence?: boolean;
  sources?: Source[];
};

export default function QuestionsScreen() {
  const { online } = useNetwork();
  const [slug, setSlug] = useState('');
  const [bookTitle, setBookTitle] = useState('');
  const [question, setQuestion] = useState('');
  const [conversationId, setConversationId] = useState<string | undefined>();
  const [result, setResult] = useState<AskResult | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!online) return;
    void apiRequest<{ data: { items: { slug: string; title?: string }[] } }>('/api/library')
      .then((r) => {
        const first = r.data.items[0];
        setSlug(first?.slug ?? '');
        setBookTitle(first?.title ?? '');
        if (!first?.slug) setError('Aucun livre publié n’est disponible.');
      })
      .catch((e) =>
        setError(e instanceof ApiError ? e.message : 'Bibliothèque indisponible.'),
      );
  }, [online]);

  return (
    <Screen>
      <ScrollView
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={{ paddingBottom: 28 }}
      >
        <Title>Demander au livre</Title>
        <Muted>
          Réponses fondées sur les passages publiés
          {bookTitle ? ` — ${bookTitle}` : ''}.
        </Muted>
        {!online ? (
          <OnlineGate message="Posez vos questions lorsque vous êtes en ligne." />
        ) : (
          <>
            <Field
              placeholder="Votre question…"
              value={question}
              onChangeText={setQuestion}
              multiline
              editable={!busy}
              style={{ minHeight: 100, textAlignVertical: 'top' }}
            />
            {error ? (
              <Text style={{ color: colors.danger, fontFamily: fonts.ui, marginBottom: 8 }}>{error}</Text>
            ) : null}
            {busy ? (
              <Card>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
                  <ActivityIndicator color={colors.accent} />
                  <View style={{ flex: 1 }}>
                    <Text style={{ fontFamily: fonts.uiBold, color: colors.ink }}>Recherche dans le livre</Text>
                    <Muted>Embedding et sélection des passages… cela peut prendre une minute.</Muted>
                  </View>
                </View>
              </Card>
            ) : null}
            <Button
              label="Envoyer"
              busy={busy}
              disabled={!slug || !question.trim() || question.trim().length < 3}
              onPress={() => {
                const q = question.trim();
                if (q.length < 3) {
                  setError('La question doit contenir au moins 3 caractères.');
                  return;
                }
                if (!slug) {
                  setError('Aucun livre publié n’est disponible.');
                  return;
                }
                setBusy(true);
                setError('');
                setResult(null);
                void apiRequest<{ data: AskResult }>(`/api/books/${slug}/questions`, {
                  method: 'POST',
                  body: JSON.stringify({ question: q, conversationId }),
                  timeoutMs: LONG_REQUEST_TIMEOUT_MS,
                })
                  .then((r) => {
                    setResult(r.data);
                    setConversationId(r.data.conversationId);
                  })
                  .catch((e) =>
                    setError(e instanceof ApiError ? e.message : 'La question n’a pas pu être traitée.'),
                  )
                  .finally(() => setBusy(false));
              }}
            />
            {result ? (
              <Card>
                <Text style={{ fontFamily: fonts.uiBold, color: colors.ink, marginBottom: 8 }}>
                  {result.insufficientEvidence ? 'Preuve insuffisante' : 'Réponse'}
                </Text>
                <Text
                  style={{
                    fontFamily: fonts.display,
                    fontSize: 16,
                    lineHeight: 26,
                    color: colors.inkSoft,
                  }}
                >
                  {result.answer}
                </Text>
                {(result.sources?.length ?? 0) > 0 ? (
                  <View style={{ marginTop: 14 }}>
                    <Text style={{ fontFamily: fonts.uiBold, color: colors.ink, marginBottom: 6 }}>
                      Passages utilisés
                    </Text>
                    {result.sources!.map((s, i) => {
                      const pages =
                        s.start_page != null
                          ? `p. ${s.start_page}${s.end_page && s.end_page !== s.start_page ? `–${s.end_page}` : ''}`
                          : '';
                      return (
                        <View
                          key={s.id}
                          style={{
                            borderTopWidth: 1,
                            borderTopColor: colors.line,
                            paddingTop: 10,
                            marginTop: 10,
                          }}
                        >
                          <Text style={{ fontFamily: fonts.uiBold, color: colors.muted, fontSize: 12 }}>
                            Source {i + 1}
                            {pages ? ` · ${pages}` : ''}
                          </Text>
                          {s.content ? (
                            <Text
                              style={{
                                fontFamily: fonts.ui,
                                color: colors.inkSoft,
                                marginTop: 4,
                                lineHeight: 20,
                              }}
                            >
                              {s.content}
                            </Text>
                          ) : null}
                        </View>
                      );
                    })}
                  </View>
                ) : null}
              </Card>
            ) : null}
          </>
        )}
      </ScrollView>
    </Screen>
  );
}
