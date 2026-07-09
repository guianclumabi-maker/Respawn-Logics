import React, { useState } from 'react';
import { View, Text, TouchableOpacity, Alert } from 'react-native';
import { Screen, Card, Title, Sub, Button, Row } from '../components/UI';
import { colors } from '../theme';
import { useAuth } from '../AuthContext';
import { getBaseUrl } from '../api';
import AnnouncementsScreen from './AnnouncementsScreen';
import NotificationsScreen from './NotificationsScreen';
import HRCasesScreen from './HRCasesScreen';
import ApprovalsScreen from './ApprovalsScreen';

const ITEMS = [
  { key: 'announcements', icon: '📣', label: 'Announcements', sub: 'Company news & posts' },
  { key: 'notifications', icon: '🔔', label: 'Notifications', sub: 'Your unread alerts' },
  { key: 'cases', icon: '🗂️', label: 'HR cases', sub: 'File and track employee relations cases' },
  { key: 'approvals', icon: '✅', label: 'Approvals', sub: 'Leave & timesheet approvals (managers)' },
];

export default function MoreScreen() {
  const { user, signOut, changeServer } = useAuth();
  const [section, setSection] = useState(null);

  if (section) {
    const item = ITEMS.find((i) => i.key === section);
    return (
      <View style={{ flex: 1, backgroundColor: colors.bg }}>
        <Row
          style={{
            paddingHorizontal: 16,
            paddingTop: 8,
            paddingBottom: 10,
            borderBottomWidth: 1,
            borderBottomColor: colors.border,
          }}
        >
          <TouchableOpacity onPress={() => setSection(null)} style={{ paddingRight: 12 }}>
            <Text style={{ color: colors.accent, fontSize: 16 }}>‹ Back</Text>
          </TouchableOpacity>
          <Text style={{ color: colors.text, fontSize: 17, fontWeight: '700' }}>{item?.label}</Text>
        </Row>
        {section === 'announcements' && <AnnouncementsScreen />}
        {section === 'notifications' && <NotificationsScreen />}
        {section === 'cases' && <HRCasesScreen />}
        {section === 'approvals' && <ApprovalsScreen />}
      </View>
    );
  }

  const confirmLogout = () => {
    Alert.alert('Sign out', 'Are you sure you want to sign out?', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Sign out', style: 'destructive', onPress: signOut },
    ]);
  };

  return (
    <Screen>
      <Card>
        <Row>
          <View
            style={{
              width: 52,
              height: 52,
              borderRadius: 26,
              backgroundColor: colors.accentSoft,
              alignItems: 'center',
              justifyContent: 'center',
              marginRight: 12,
            }}
          >
            <Text style={{ color: colors.text, fontSize: 20, fontWeight: '800' }}>
              {(user?.name || '?').charAt(0).toUpperCase()}
            </Text>
          </View>
          <View style={{ flex: 1 }}>
            <Title style={{ marginBottom: 0 }}>{user?.name}</Title>
            <Sub>{user?.job_title || (user?.roles || []).join(', ') || 'Employee'}</Sub>
          </View>
        </Row>
        <Sub style={{ marginTop: 10 }}>Server: {getBaseUrl()}</Sub>
      </Card>

      {ITEMS.map((item) => (
        <TouchableOpacity key={item.key} onPress={() => setSection(item.key)}>
          <Card style={{ paddingVertical: 14 }}>
            <Row>
              <Text style={{ fontSize: 20, marginRight: 12 }}>{item.icon}</Text>
              <View style={{ flex: 1 }}>
                <Text style={{ color: colors.text, fontWeight: '700' }}>{item.label}</Text>
                <Sub>{item.sub}</Sub>
              </View>
              <Text style={{ color: colors.sub, fontSize: 18 }}>›</Text>
            </Row>
          </Card>
        </TouchableOpacity>
      ))}

      <Button label="Sign out" variant="danger" onPress={confirmLogout} style={{ marginTop: 8 }} />
      <TouchableOpacity onPress={changeServer} style={{ marginTop: 16, alignItems: 'center' }}>
        <Text style={{ color: colors.sub, fontSize: 13 }}>Switch server</Text>
      </TouchableOpacity>
    </Screen>
  );
}
