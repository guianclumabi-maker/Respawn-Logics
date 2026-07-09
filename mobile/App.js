import React, { useState } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { SafeAreaView } from 'react-native';
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
  { key: 'home', label: 'Home', icon: '🏠', component: HomeScreen },
  { key: 'attendance', label: 'Time', icon: '⏱️', component: AttendanceScreen },
  { key: 'leaves', label: 'Leaves', icon: '🌴', component: LeavesScreen },
  { key: 'payslips', label: 'Pay', icon: '💸', component: PayslipsScreen },
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
        {TABS.map((t) => (
          <TouchableOpacity key={t.key} style={styles.tabItem} onPress={() => setActive(t.key)}>
            <Text style={{ fontSize: 20, opacity: active === t.key ? 1 : 0.45 }}>{t.icon}</Text>
            <Text
              style={{
                fontSize: 11,
                marginTop: 2,
                color: active === t.key ? colors.accent : colors.sub,
                fontWeight: active === t.key ? '700' : '400',
              }}
            >
              {t.label}
            </Text>
          </TouchableOpacity>
        ))}
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
        <StatusBar style="light" />
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
    paddingBottom: 18,
    paddingTop: 8,
  },
  tabItem: {
    flex: 1,
    alignItems: 'center',
  },
});
