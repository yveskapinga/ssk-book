import { useState } from 'react';
import { Image, Text, View } from 'react-native';
import { Link, router } from 'expo-router';
import { useAuth } from '@/src/lib/auth';
import { ApiError } from '@/src/lib/api';
import { Button, Field, Muted, Screen, Title } from '@/src/components/ui';
import { colors, fonts } from '@/src/lib/theme';

export default function LoginScreen() {
  const { login } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  return (
    <Screen padTop padBottom style={{ justifyContent: 'center', backgroundColor: colors.sidebar }}>
      <View style={{ backgroundColor: colors.paper, borderRadius: 16, padding: 22 }}>
        <View style={{ alignItems: 'center', marginBottom: 12 }}>
          <Image
            source={require('../../assets/images/logo.png')}
            style={{ width: 56, height: 56, borderRadius: 12, marginBottom: 10 }}
          />
          <Text style={{ fontFamily: fonts.displayBold, fontSize: 28, color: colors.ink }}>
            SSK <Text style={{ color: colors.gold }}>Book</Text>
          </Text>
        </View>
        <Title>Connexion</Title>
        <Muted>Une fois connecté, votre session reste disponible hors ligne.</Muted>
        <Field
          autoCapitalize="none"
          keyboardType="email-address"
          placeholder="E-mail"
          value={email}
          onChangeText={setEmail}
        />
        <Field
          secureTextEntry
          placeholder="Mot de passe"
          value={password}
          onChangeText={setPassword}
        />
        {error ? <Text style={{ color: colors.danger, marginBottom: 8, fontFamily: fonts.ui }}>{error}</Text> : null}
        <Button
          label="Se connecter"
          busy={busy}
          onPress={() => {
            setBusy(true);
            setError('');
            void login(email.trim(), password)
              .then(() => router.replace('/'))
              .catch((err) => setError(err instanceof ApiError ? err.message : 'Connexion impossible.'))
              .finally(() => setBusy(false));
          }}
        />
        <Link href="/(auth)/register" style={{ marginTop: 16, textAlign: 'center', color: colors.accent, fontFamily: fonts.uiBold }}>
          Créer un compte
        </Link>
      </View>
    </Screen>
  );
}
