import { useState } from 'react';
import { Alert, Linking, Pressable, Text, View } from 'react-native';
import { useAuth } from '@/src/lib/auth';
import { apiRequest, ApiError } from '@/src/lib/api';
import { LEGAL_URLS } from '@/src/lib/legal';
import { scheduleReadingReminder } from '@/src/lib/push';
import { Button, Card, Muted, Screen, Title } from '@/src/components/ui';
import { colors, fonts } from '@/src/lib/theme';

async function openUrl(url: string) {
  try {
    await Linking.openURL(url);
  } catch {
    Alert.alert('Lien indisponible', url);
  }
}

export default function ProfileScreen() {
  const { user, logout } = useAuth();
  const [busy, setBusy] = useState(false);

  const confirmDelete = () => {
    Alert.alert(
      'Supprimer mon compte',
      'Cette action anonymise votre compte et efface progression, favoris, notes, questions et quiz. Elle est irréversible.',
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: 'Supprimer',
          style: 'destructive',
          onPress: () => {
            setBusy(true);
            void apiRequest('/api/auth/delete-account', {
              method: 'POST',
              body: JSON.stringify({ confirm: 'DELETE' }),
            })
              .then(async () => {
                await logout();
                Alert.alert('Compte supprimé', 'Vos données personnelles ont été traitées.');
              })
              .catch((err) => {
                Alert.alert(
                  'Suppression impossible',
                  err instanceof ApiError ? err.message : 'Réessayez ou contactez yveskapinga@gmail.com',
                );
              })
              .finally(() => setBusy(false));
          },
        },
      ],
    );
  };

  return (
    <Screen>
      <Title>Profil</Title>
      <Card>
        <Text style={{ fontFamily: fonts.displayBold, fontSize: 22, lineHeight: 28, color: colors.ink }}>
          {user?.displayName}
        </Text>
        <Muted>{user?.email}</Muted>
        <Text style={{ fontFamily: fonts.ui, color: colors.muted, marginBottom: 8, lineHeight: 20 }}>
          Session durable — déconnexion uniquement ici.
        </Text>
        <Button
          label="Rappel lecture (24 h)"
          variant="secondary"
          onPress={() => void scheduleReadingReminder()}
        />
        <Button label="Se déconnecter" variant="ghost" onPress={() => void logout()} />
      </Card>

      <Card>
        <Text style={{ fontFamily: fonts.uiBold, color: colors.ink, marginBottom: 8 }}>Informations légales</Text>
        <View style={{ gap: 10 }}>
          <Pressable onPress={() => void openUrl(LEGAL_URLS.privacy)}>
            <Text style={{ color: colors.accent, fontFamily: fonts.uiBold }}>Politique de confidentialité</Text>
          </Pressable>
          <Pressable onPress={() => void openUrl(LEGAL_URLS.terms)}>
            <Text style={{ color: colors.accent, fontFamily: fonts.uiBold }}>Conditions d’utilisation</Text>
          </Pressable>
          <Pressable onPress={() => void openUrl(LEGAL_URLS.deleteAccount)}>
            <Text style={{ color: colors.accent, fontFamily: fonts.uiBold }}>
              Suppression du compte (page web)
            </Text>
          </Pressable>
        </View>
        <Button
          label="Supprimer mon compte"
          variant="secondary"
          busy={busy}
          onPress={confirmDelete}
        />
        <Muted>Exigence Google Play : suppression disponible dans l’app et via une URL publique.</Muted>
      </Card>
    </Screen>
  );
}
