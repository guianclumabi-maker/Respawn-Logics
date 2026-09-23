import React, { useState } from 'react';
import { View, Text, TouchableOpacity, StyleSheet, SafeAreaView } from 'react-native';
import { StatusBar } from 'expo-status-bar';
import { AuthProvider, useAuth } from './src/AuthContext';
import { colors } from './src/theme';
import { LoadingView } from './src/components/UI';
import SetupScreen from './src/screens/SetupScreen';
import LoginScreen from './src/screens/LoginScreen';
import HomeScreen from './src/screens/HomeScreen';
import AttendanceScreen from './src/screens/AttendanceScreen';
import LeavesScreen from './src/screens/LeavesScreen';
import PayslipsScreen from './src/screens/PayslipsScreen';
import MoreScreen from './src/screens/MoreScreen';

const TABS = [
  { key: 'home', label: 'Home', icon: '⚡', component: HomeScreen },
  { key: 'attendance', label: 'Time', icon: '⏱️', component: AttendanceScreen },
  { key: 'leaves', label: 'Leaves', icon: '🌴', component: LeavesScreen },
  { key: 'payslips', label: 'Pay', icon: '💳', component: PayslipsScreen },
  { key: 'more', label: 'More', icon: '☰', component: MoreScreen },
];

function MainTabs() {
  const [active, setActive] = useState('home');
  const Active = TABS.find((t) => t.key === active).component;

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <View style={{ flex: 1 }}>
        <Active />
      </View>
      <View style={styles.tabBar}>
        {TABS.map((t) => {
          const isActive = active === t.key;
          return (
            <TouchableOpacity
              key={t.key}
              activeOpacity={0.7}
              style={styles.tabItem}
              onPress={() => setActive(t.key)}
            >
              {isActive && <View style={styles.activeTabIndicator} />}
              <Text style={{ fontSize: 18, opacity: isActive ? 1 : 0.45 }}>{t.icon}</Text>
              <Text
                style={{
                  fontSize: 11,
                  marginTop: 3,
                  color: isActive ? colors.accent : colors.subMuted,
                  fontWeight: isActive ? '700' : '500',
                  letterSpacing: 0.2,
                }}
              >
                {t.label}
              </Text>
            </TouchableOpacity>
          );
        })}
      </View>
    </View>
  );
}

function Root() {
  const { booting, serverUrl, user } = useAuth();
  if (booting) return <LoadingView />;
  if (!serverUrl) return <SetupScreen />;
  if (!user) return <LoginScreen />;
  return <MainTabs />;
}

export default function App() {
  return (
    <AuthProvider>
      <SafeAreaView style={{ flex: 1, backgroundColor: colors.bg }}>
        <StatusBar style="light" backgroundColor={colors.bg} />
        <Root />
      </SafeAreaView>
    </AuthProvider>
  );
}

const styles = StyleSheet.create({
  tabBar: {
    flexDirection: 'row',
    borderTopWidth: 1,
    borderTopColor: colors.border,
    backgroundColor: colors.card,
    paddingBottom: 20,
    paddingTop: 10,
  },
  tabItem: {
    flex: 1,
    alignItems: 'center',
    position: 'relative',
  },
  activeTabIndicator: {
    position: 'absolute',
    top: -10,
    width: 24,
    height: 3,
    borderRadius: 2,
    backgroundColor: colors.accent,
  },
});
