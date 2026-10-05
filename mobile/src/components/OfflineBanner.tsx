import { Text, View } from 'react-native';
import { useNetwork } from '@/src/lib/offline/status';
import { colors, fonts } from '@/src/lib/theme';

export function OfflineBanner() {
  const { online } = useNetwork();
  if (online) return null;
  return (
    <View
      style={{
        backgroundColor: colors.ink,
        paddingVertical: 8,
        paddingHorizontal: 14,
      }}
    >
      <Text style={{ color: '#fff', fontFamily: fonts.uiBold, fontSize: 13, textAlign: 'center' }}>
        Hors ligne — lecture et progression locales disponibles
      </Text>
    </View>
  );
}
