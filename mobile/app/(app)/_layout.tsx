import { Redirect, Tabs, router, usePathname } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { ActivityIndicator, Pressable, Text, View } from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '@/src/lib/auth';
import { OfflineBanner } from '@/src/components/OfflineBanner';
import { colors, fonts } from '@/src/lib/theme';
import { useResponsiveLayout } from '@/hooks/useResponsiveLayout';

type TabDef = {
  name: string;
  title: string;
  icon: keyof typeof Ionicons.glyphMap;
  href?: string;
};

const tabs: TabDef[] = [
  { name: 'index', title: 'Accueil', icon: 'home-outline', href: '/' },
  { name: 'reading', title: 'Lecture', icon: 'book-outline' },
  { name: 'quiz', title: 'Quiz', icon: 'help-circle-outline' },
  { name: 'questions', title: 'Q&R', icon: 'chatbubble-ellipses-outline' },
  { name: 'more', title: 'Plus', icon: 'menu-outline' },
];

export default function AppLayout() {
  const { user, ready } = useAuth();
  const layout = useResponsiveLayout();
  const pathname = usePathname();
  const insets = useSafeAreaInsets();
  const tabBarBottom = Math.max(insets.bottom, 8);

  if (!ready) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.paper }}>
        <ActivityIndicator color={colors.accent} />
      </View>
    );
  }
  if (!user) return <Redirect href="/(auth)/login" />;

  if (layout.isTabletDevice) {
    return (
      <SafeAreaView style={{ flex: 1, flexDirection: 'row', backgroundColor: colors.paper }} edges={['top', 'left', 'bottom']}>
        <View
          style={{
            width: layout.navRailWidth,
            backgroundColor: colors.sidebar,
            paddingTop: 20,
            paddingHorizontal: 10,
            overflow: 'visible',
          }}
        >
          <Text
            style={{
              fontFamily: fonts.displayBold,
              fontSize: 22,
              lineHeight: 28,
              color: colors.sidebarText,
              paddingHorizontal: 8,
              marginBottom: 20,
            }}
          >
            SSK <Text style={{ color: colors.gold }}>Book</Text>
          </Text>
          {tabs.map((tab) => {
            const href = tab.href ?? `/${tab.name}`;
            const active = pathname === href || pathname.startsWith(`/${tab.name}`);
            return (
              <Pressable
                key={tab.name}
                onPress={() => router.push(href as never)}
                style={{
                  flexDirection: 'row',
                  alignItems: 'center',
                  gap: 10,
                  paddingVertical: 12,
                  paddingHorizontal: 10,
                  borderRadius: 10,
                  marginBottom: 4,
                  backgroundColor: active ? colors.gold : 'transparent',
                }}
              >
                <Ionicons name={tab.icon} size={20} color={active ? colors.ink : '#e7f0ff'} />
                <Text
                  style={{
                    flex: 1,
                    flexShrink: 1,
                    fontFamily: fonts.uiBold,
                    color: active ? colors.ink : '#e7f0ff',
                    fontSize: 15,
                    lineHeight: 20,
                  }}
                  numberOfLines={1}
                >
                  {tab.title}
                </Text>
              </Pressable>
            );
          })}
          <View style={{ marginTop: 'auto', padding: 12, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.12)' }}>
            <Text style={{ color: colors.sidebarText, fontFamily: fonts.uiBold }} numberOfLines={1}>
              {user.displayName}
            </Text>
            <Text style={{ color: colors.sidebarMuted, fontSize: 12, marginTop: 2 }}>Lecteur</Text>
          </View>
        </View>
        <View style={{ flex: 1 }}>
          <OfflineBanner />
          <Tabs
            screenOptions={{
              headerShown: false,
              tabBarStyle: { display: 'none' },
            }}
          >
            {tabs.map((t) => (
              <Tabs.Screen key={t.name} name={t.name} options={{ title: t.title }} />
            ))}
            <Tabs.Screen name="progress" options={{ href: null }} />
            <Tabs.Screen name="profile" options={{ href: null }} />
            <Tabs.Screen name="notifications" options={{ href: null }} />
          </Tabs>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={{ flex: 1, backgroundColor: colors.paper }} edges={['top']}>
      <OfflineBanner />
      <Tabs
        screenOptions={{
          headerShown: false,
          tabBarActiveTintColor: colors.ink,
          tabBarInactiveTintColor: colors.muted,
          tabBarActiveBackgroundColor: colors.gold,
          tabBarHideOnKeyboard: true,
          tabBarItemStyle: {
            borderRadius: 10,
            marginHorizontal: 2,
            marginVertical: 4,
            paddingTop: 2,
          },
          tabBarStyle: {
            backgroundColor: colors.panel,
            borderTopColor: colors.line,
            height: 62 + tabBarBottom,
            paddingTop: 4,
            paddingBottom: tabBarBottom,
          },
          tabBarLabelStyle: {
            fontFamily: fonts.uiBold,
            fontSize: 11,
            lineHeight: 14,
            marginBottom: 2,
          },
          tabBarIconStyle: { marginBottom: 0 },
        }}
      >
        {tabs.map((t) => (
          <Tabs.Screen
            key={t.name}
            name={t.name}
            options={{
              title: t.title,
              tabBarLabel: t.title,
              tabBarIcon: ({ color, size }) => <Ionicons name={t.icon} size={size} color={color} />,
              tabBarAccessibilityLabel: t.name === 'questions' ? 'Questions' : t.title,
            }}
          />
        ))}
        <Tabs.Screen name="progress" options={{ href: null }} />
        <Tabs.Screen name="profile" options={{ href: null }} />
        <Tabs.Screen name="notifications" options={{ href: null }} />
      </Tabs>
    </SafeAreaView>
  );
}
