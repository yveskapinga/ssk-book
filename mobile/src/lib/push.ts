import { Platform } from 'react-native';
import Constants from 'expo-constants';
import { apiRequest } from '@/src/lib/api';
import { appendLocalNotification } from '@/src/lib/offline/db';

/** Expo Go (SDK 53+) n’expose plus les push Android — no-op jusqu’au dev build / APK. */
const isExpoGo =
  Constants.appOwnership === 'expo' ||
  Constants.executionEnvironment === 'storeClient';

function loadNotifications(): typeof import('expo-notifications') | null {
  if (isExpoGo) return null;
  try {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    return require('expo-notifications') as typeof import('expo-notifications');
  } catch {
    return null;
  }
}

export async function registerForPush(): Promise<string | null> {
  const Notifications = loadNotifications();
  if (!Notifications) return null;

  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const Device = require('expo-device') as typeof import('expo-device');
  if (!Device.isDevice && Platform.OS === 'ios') return null;

  const { status: existing } = await Notifications.getPermissionsAsync();
  let finalStatus = existing;
  if (existing !== 'granted') {
    const req = await Notifications.requestPermissionsAsync();
    finalStatus = req.status;
  }
  if (finalStatus !== 'granted') return null;

  if (Platform.OS === 'android') {
    await Notifications.setNotificationChannelAsync('default', {
      name: 'SSK Book',
      importance: Notifications.AndroidImportance.DEFAULT,
      lightColor: '#0b4aa2',
    });
  }

  const projectId =
    Constants.easConfig?.projectId ?? Constants.expoConfig?.extra?.eas?.projectId;
  try {
    const token = (
      await Notifications.getExpoPushTokenAsync(projectId ? { projectId } : undefined)
    ).data;
    await apiRequest('/api/me/push-tokens', {
      method: 'POST',
      body: JSON.stringify({
        token,
        platform: Platform.OS === 'ios' ? 'ios' : 'android',
      }),
    });
    return token;
  } catch {
    return null;
  }
}

export function attachNotificationListeners(): () => void {
  const Notifications = loadNotifications();
  if (!Notifications) return () => undefined;

  Notifications.setNotificationHandler({
    handleNotification: async () => ({
      shouldShowBanner: true,
      shouldShowList: true,
      shouldPlaySound: true,
      shouldSetBadge: false,
    }),
  });

  const sub = Notifications.addNotificationReceivedListener((n) => {
    void appendLocalNotification({
      id: String(n.request.identifier),
      title: n.request.content.title ?? 'SSK Book',
      body: n.request.content.body ?? '',
      at: Date.now(),
      data: (n.request.content.data ?? {}) as Record<string, unknown>,
    });
  });
  return () => sub.remove();
}

export async function scheduleReadingReminder(): Promise<void> {
  const Notifications = loadNotifications();
  if (!Notifications) return;
  await Notifications.scheduleNotificationAsync({
    content: {
      title: 'Reprendre la lecture',
      body: 'Votre prochain passage vous attend dans SSK Book.',
      data: { type: 'reading', url: '/reading' },
    },
    trigger: {
      type: Notifications.SchedulableTriggerInputTypes.TIME_INTERVAL,
      seconds: 60 * 60 * 24,
      repeats: false,
    },
  });
}
