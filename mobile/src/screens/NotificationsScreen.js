import React, { useCallback, useEffect, useState } from 'react';
import { View, Text, TouchableOpacity } from 'react-native';
import { Screen, Card, Sub, Button, EmptyState, Chip } from '../components/UI';
import { colors } from '../theme';
import * as api from '../api';

export default function NotificationsScreen() {
  const [items, setItems] = useState([]);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    try {
      const { data } = await api.getUnreadNotifications();
      if (data && data.success) setItems(data.data || []);
    } catch (e) {
      // ignore
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const onRefresh = async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  };

  const readOne = async (id) => {
    await api.markNotificationRead(id);
    setItems((prev) => prev.filter((n) => n.id !== id));
  };

  const readAll = async () => {
    await api.markAllNotificationsRead();
    setItems([]);
  };

  return (
    <Screen refreshing={refreshing} onRefresh={onRefresh}>
      {items.length > 0 && (
        <Button label="Mark All as Read" variant="secondary" onPress={readAll} style={{ marginBottom: 14 }} icon="✓" />
      )}
      {items.length === 0 ? (
        <EmptyState text="You're all caught up 🎉" icon="🔔" />
      ) : (
        items.map((n) => (
          <TouchableOpacity key={n.id} onPress={() => readOne(n.id)} activeOpacity={0.8}>
            <Card style={{ paddingVertical: 14 }} accentColor={colors.warning}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 }}>
                <Text style={{ color: colors.text, fontWeight: '800', fontSize: 15, flex: 1, paddingRight: 10 }}>
                  {n.title}
                </Text>
                <Chip label="NEW" color={colors.warning} />
              </View>
              <Sub style={{ color: colors.textSecondary, marginTop: 2, fontSize: 13, lineHeight: 18 }}>
                {n.message}
              </Sub>
              <Sub style={{ marginTop: 8, fontSize: 11, color: colors.subMuted }}>
                ⏰ {n.created_at} • Tap card to dismiss
              </Sub>
            </Card>
          </TouchableOpacity>
        ))
      )}
    </Screen>
  );
}
