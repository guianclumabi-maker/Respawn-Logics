import React, { useState } from 'react';
import { View, Text, TouchableOpacity, Alert } from 'react-native';
import { Screen, Card, Title, Sub, Button, Row, BrandHeader, Chip } from '../components/UI';
import { colors } from '../theme';
import { useAuth } from '../AuthContext';
import { getBaseUrl } from '../api';
import AnnouncementsScreen from './AnnouncementsScreen';
import NotificationsScreen from './NotificationsScreen';
import HRCasesScreen from './HRCasesScreen';
import ApprovalsScreen from './ApprovalsScreen';

const ITEMS = [
  { key: 'announcements', icon: '📣', label: 'Announcements', sub: 'Company updates & news feed', color: colors.accent },
  { key: 'notifications', icon: '🔔', label: 'Notifications', sub: 'Alerts & unread messages', color: colors.warning },
  { key: 'cases', icon: '🗂️', label: 'HR Cases & Support', sub: 'File & monitor HR cases', color: colors.purple },
  { key: 'approvals', icon: '✅', label: 'Manager Approvals', sub: 'Leave & timesheet queue', color: colors.info },
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
            paddingTop: 12,
            paddingBottom: 12,
            borderBottomWidth: 1,
            borderBottomColor: colors.border,
            backgroundColor: colors.card,
            alignItems: 'center',
          }}
        >
          <TouchableOpacity onPress={() => setSection(null)} activeOpacity={0.7} style={{ paddingRight: 14 }}>
            <Text style={{ color: colors.accent, fontSize: 16, fontWeight: '700' }}>‹ Back</Text>
          </TouchableOpacity>
          <Text style={{ color: colors.text, fontSize: 17, fontWeight: '800' }}>{item?.label}</Text>
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
      <BrandHeader title="Account & Modules" subtitle="Manage your profile settings and access extended tools" />

      {/* Profile Card */}
      <Card accentColor={colors.accent}>
        <Row>
          <View
            style={{
              width: 54,
              height: 54,
              borderRadius: 14,
              backgroundColor: colors.accentSoft,
              borderWidth: 1,
              borderColor: colors.accentGlow,
              alignItems: 'center',
              justifyContent: 'center',
              marginRight: 14,
            }}
          >
            <Text style={{ color: colors.accent, fontSize: 22, fontWeight: '900' }}>
              {(user?.name || '?').charAt(0).toUpperCase()}
            </Text>
          </View>
          <View style={{ flex: 1 }}>
            <Title style={{ marginBottom: 2, fontSize: 18 }}>{user?.name}</Title>
            <Sub style={{ fontSize: 13 }}>{user?.job_title || (user?.roles || []).join(', ') || 'Employee'}</Sub>
            {user?.email ? <Sub style={{ marginTop: 2, color: colors.subMuted, fontSize: 12 }}>{user.email}</Sub> : null}
          </View>
        </Row>
        <View style={{ marginTop: 12, paddingTop: 10, borderTopWidth: 1, borderTopColor: colors.border }}>
          <Sub style={{ fontSize: 12, color: colors.subMuted }}>Connected Server: {getBaseUrl()}</Sub>
        </View>
      </Card>

      <Title style={{ marginTop: 8, marginBottom: 10, fontSize: 16 }}>HR Modules</Title>
      {ITEMS.map((item) => (
        <TouchableOpacity key={item.key} onPress={() => setSection(item.key)} activeOpacity={0.8}>
          <Card style={{ paddingVertical: 14 }} accentColor={item.color}>
            <Row>
              <View
                style={{
                  width: 40,
                  height: 40,
                  borderRadius: 10,
                  backgroundColor: colors.cardAlt,
                  alignItems: 'center',
                  justifyContent: 'center',
                  marginRight: 14,
                }}
              >
                <Text style={{ fontSize: 20 }}>{item.icon}</Text>
              </View>
              <View style={{ flex: 1 }}>
                <Text style={{ color: colors.text, fontWeight: '700', fontSize: 15 }}>{item.label}</Text>
                <Sub style={{ marginTop: 2 }}>{item.sub}</Sub>
              </View>
              <Text style={{ color: colors.subMuted, fontSize: 20 }}>›</Text>
            </Row>
          </Card>
        </TouchableOpacity>
      ))}

      <View style={{ marginTop: 12 }}>
        <Button label="Sign Out of Account" variant="danger" onPress={confirmLogout} icon="🚪" />
        <TouchableOpacity onPress={changeServer} style={{ marginTop: 16, alignItems: 'center' }}>
          <Text style={{ color: colors.sub, fontSize: 13, fontWeight: '600' }}>Switch Server Connection</Text>
        </TouchableOpacity>
      </View>
    </Screen>
  );
}
