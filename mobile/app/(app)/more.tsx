import { router } from 'expo-router';
import { Screen, Title, Muted, Button, Card } from '@/src/components/ui';

export default function MoreScreen() {
  return (
    <Screen>
      <Title>Plus</Title>
      <Muted>Suivi, profil et notifications.</Muted>
      <Card>
        <Button label="Suivi" variant="secondary" onPress={() => router.push('/progress')} />
        <Button label="Notifications" variant="secondary" onPress={() => router.push('/notifications')} />
        <Button label="Profil" variant="ghost" onPress={() => router.push('/profile')} />
      </Card>
    </Screen>
  );
}
