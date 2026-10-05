import { Text } from 'react-native';
import { useAuth } from '@/src/lib/auth';
import { scheduleReadingReminder } from '@/src/lib/push';
import { Button, Card, Muted, Screen, Title } from '@/src/components/ui';
import { colors, fonts } from '@/src/lib/theme';

export default function ProfileScreen() {
  const { user, logout } = useAuth();

  return (
    <Screen>
      <Title>Profil</Title>
      <Card>
        <Text style={{ fontFamily: fonts.displayBold, fontSize: 22, color: colors.ink }}>{user?.displayName}</Text>
        <Muted>{user?.email}</Muted>
        <Text style={{ fontFamily: fonts.ui, color: colors.muted, marginBottom: 8 }}>
          Session durable — déconnexion uniquement ici.
        </Text>
        <Button
          label="Rappel lecture (24 h)"
          variant="secondary"
          onPress={() => void scheduleReadingReminder()}
        />
        <Button label="Se déconnecter" variant="ghost" onPress={() => void logout()} />
      </Card>
    </Screen>
  );
}
