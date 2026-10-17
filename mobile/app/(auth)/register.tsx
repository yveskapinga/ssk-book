import { useState } from 'react';
import { Text } from 'react-native';
import { Link, router } from 'expo-router';
import { useAuth } from '@/src/lib/auth';
import { ApiError } from '@/src/lib/api';
import { Button, Field, Muted, Screen, Title } from '@/src/components/ui';
import { colors, fonts } from '@/src/lib/theme';

export default function RegisterScreen() {
  const { register } = useAuth();
  const [displayName, setDisplayName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  return (
    <Screen style={{ justifyContent: 'center' }}>
      <Title>Inscription</Title>
      <Muted>Créez votre compte lecteur (mot de passe ≥ 10 caractères).</Muted>
      <Field placeholder="Nom affiché" value={displayName} onChangeText={setDisplayName} />
      <Field
        autoCapitalize="none"
        keyboardType="email-address"
        placeholder="E-mail"
        value={email}
        onChangeText={setEmail}
      />
      <Field secureTextEntry placeholder="Mot de passe" value={password} onChangeText={setPassword} />
      {error ? <Text style={{ color: colors.danger, marginBottom: 8, fontFamily: fonts.ui }}>{error}</Text> : null}
      <Button
        label="Créer mon compte"
        busy={busy}
        onPress={() => {
          setBusy(true);
          setError('');
          void register(displayName.trim(), email.trim(), password)
            .then(() => router.replace('/'))
            .catch((err) => setError(err instanceof ApiError ? err.message : 'Inscription impossible.'))
            .finally(() => setBusy(false));
        }}
      />
      <Link href="/(auth)/login" style={{ marginTop: 16, textAlign: 'center', color: colors.accent, fontFamily: fonts.uiBold }}>
        Déjà un compte ? Connexion
      </Link>
    </Screen>
  );
}
