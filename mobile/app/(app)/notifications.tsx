import { useEffect, useState } from 'react';
import { ScrollView, Text } from 'react-native';
import { listLocalNotifications } from '@/src/lib/offline/db';
import { Card, Muted, Screen, Title } from '@/src/components/ui';
import { colors, fonts } from '@/src/lib/theme';

export default function NotificationsScreen() {
  const [items, setItems] = useState<
    { id: string; title: string; body: string; at: number }[]
  >([]);

  useEffect(() => {
    void listLocalNotifications().then(setItems);
  }, []);

  return (
    <Screen>
      <ScrollView>
        <Title>Notifications</Title>
        <Muted>Historique local des alertes reçues.</Muted>
        {items.length === 0 ? (
          <Muted>Aucune notification pour le moment.</Muted>
        ) : (
          items.map((n) => (
            <Card key={n.id}>
              <Text style={{ fontFamily: fonts.uiBold, color: colors.ink }}>{n.title}</Text>
              <Text style={{ fontFamily: fonts.ui, color: colors.inkSoft, marginTop: 4 }}>{n.body}</Text>
              <Text style={{ color: colors.muted, fontSize: 12, marginTop: 8 }}>
                {new Date(n.at).toLocaleString()}
              </Text>
            </Card>
          ))
        )}
      </ScrollView>
    </Screen>
  );
}
