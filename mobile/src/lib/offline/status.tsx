import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import NetInfo from '@react-native-community/netinfo';
import { getToken } from '@/src/lib/storage';
import { flushPending } from '@/src/lib/offline/sync';

type NetValue = { online: boolean };

const NetContext = createContext<NetValue>({ online: true });

export function NetworkProvider({ children }: { children: React.ReactNode }) {
  const [online, setOnline] = useState(true);

  useEffect(() => {
    const unsub = NetInfo.addEventListener((state) => {
      const isOn = !!(state.isConnected && state.isInternetReachable !== false);
      setOnline(isOn);
      if (isOn && getToken()) void flushPending();
    });
    void NetInfo.fetch().then((state) => {
      setOnline(!!(state.isConnected && state.isInternetReachable !== false));
    });
    return () => unsub();
  }, []);

  const value = useMemo(() => ({ online }), [online]);
  return <NetContext.Provider value={value}>{children}</NetContext.Provider>;
}

export function useNetwork(): NetValue {
  return useContext(NetContext);
}
