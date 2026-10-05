import React from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
  type TextInputProps,
  type ViewStyle,
} from 'react-native';
import { colors, fonts } from '@/src/lib/theme';

export function Screen({ children, style }: { children: React.ReactNode; style?: ViewStyle }) {
  return <View style={[styles.screen, style]}>{children}</View>;
}

export function Title({ children }: { children: React.ReactNode }) {
  return <Text style={styles.title}>{children}</Text>;
}

export function Muted({ children }: { children: React.ReactNode }) {
  return <Text style={styles.muted}>{children}</Text>;
}

export function Card({ children }: { children: React.ReactNode }) {
  return <View style={styles.card}>{children}</View>;
}

export function Button({
  label,
  onPress,
  variant = 'primary',
  disabled,
  busy,
}: {
  label: string;
  onPress: () => void;
  variant?: 'primary' | 'secondary' | 'ghost';
  disabled?: boolean;
  busy?: boolean;
}) {
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled || busy}
      style={({ pressed }) => [
        styles.btn,
        variant === 'primary' && styles.btnPrimary,
        variant === 'secondary' && styles.btnSecondary,
        variant === 'ghost' && styles.btnGhost,
        (disabled || busy) && { opacity: 0.42 },
        pressed && { opacity: 0.85 },
      ]}
    >
      {busy ? (
        <ActivityIndicator color={variant === 'primary' ? colors.ink : colors.accent} />
      ) : (
        <Text
          style={[
            styles.btnText,
            variant === 'primary' && { color: colors.ink },
            variant !== 'primary' && { color: colors.accent },
          ]}
        >
          {label}
        </Text>
      )}
    </Pressable>
  );
}

export function Field(props: TextInputProps) {
  return <TextInput {...props} placeholderTextColor={colors.muted} style={[styles.input, props.style]} />;
}

export function OnlineGate({ message }: { message: string }) {
  return (
    <Card>
      <Text style={{ fontFamily: fonts.uiBold, color: colors.ink, marginBottom: 6 }}>Connexion requise</Text>
      <Muted>{message}</Muted>
    </Card>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.paper, padding: 16 },
  title: {
    fontFamily: fonts.displayBold,
    fontSize: 28,
    color: colors.ink,
    letterSpacing: -0.5,
    marginBottom: 8,
  },
  muted: { fontFamily: fonts.ui, color: colors.muted, lineHeight: 22, marginBottom: 16 },
  card: {
    backgroundColor: colors.panel,
    borderColor: colors.line,
    borderWidth: 1,
    borderRadius: 14,
    padding: 16,
    marginBottom: 12,
  },
  btn: {
    minHeight: 48,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 18,
    marginTop: 10,
  },
  btnPrimary: { backgroundColor: colors.gold, borderWidth: 1, borderColor: colors.gold },
  btnSecondary: { backgroundColor: colors.panel, borderWidth: 1, borderColor: colors.accent },
  btnGhost: { backgroundColor: 'transparent' },
  btnText: { fontFamily: fonts.uiBold, fontSize: 15 },
  input: {
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.panel,
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontFamily: fonts.ui,
    color: colors.ink,
    marginBottom: 10,
  },
});
