import React, { useEffect, useState } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { StatusBar } from 'expo-status-bar';
import {
  Figtree_400Regular,
  Figtree_700Bold,
  useFonts as useFigtree,
} from '@expo-google-fonts/figtree';
import {
  Newsreader_400Regular,
  Newsreader_600SemiBold,
  useFonts as useNewsreader,
} from '@expo-google-fonts/newsreader';
import { AuthProvider } from '@/src/lib/auth';
import { NetworkProvider, useNetwork } from '@/src/lib/offline/status';
import { hydrateSession } from '@/src/lib/storage';
import { attachNotificationListeners } from '@/src/lib/push';
import { colors } from '@/src/lib/theme';

function Boot({ children }: { children: React.ReactNode }) {
  const { online } = useNetwork();
  const [booted, setBooted] = useState(false);
  const [figtreeLoaded] = useFigtree({ Figtree_400Regular, Figtree_700Bold });
  const [newsreaderLoaded] = useNewsreader({ Newsreader_400Regular, Newsreader_600SemiBold });
  const fontsReady = figtreeLoaded && newsreaderLoaded;

  useEffect(() => {
    let cancelled = false;
    void hydrateSession().finally(() => {
      if (!cancelled) setBooted(true);
    });
    const t = setTimeout(() => {
      if (!cancelled) setBooted(true);
    }, 2500);
    return () => {
      cancelled = true;
      clearTimeout(t);
    };
  }, [online]);

  useEffect(() => attachNotificationListeners(), []);

  if (!booted || !fontsReady) {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.paper }}>
        <ActivityIndicator color={colors.accent} />
      </View>
    );
  }
  return <>{children}</>;
}

export function AppProviders({ children }: { children: React.ReactNode }) {
  return (
    <SafeAreaProvider>
      <StatusBar style="dark" />
      <NetworkProvider>
        <AuthProvider>
          <Boot>{children}</Boot>
        </AuthProvider>
      </NetworkProvider>
    </SafeAreaProvider>
  );
}
