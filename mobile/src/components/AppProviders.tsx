import React, { useEffect, useState } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { AuthProvider } from '@/src/lib/auth';
import { NetworkProvider, useNetwork } from '@/src/lib/offline/status';
import { hydrateSession } from '@/src/lib/storage';
import { attachNotificationListeners } from '@/src/lib/push';
import { colors } from '@/src/lib/theme';

function Boot({ children }: { children: React.ReactNode }) {
  const { online } = useNetwork();
  const [booted, setBooted] = useState(false);

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

  if (!booted) {
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
    <NetworkProvider>
      <AuthProvider>
        <Boot>{children}</Boot>
      </AuthProvider>
    </NetworkProvider>
  );
}
