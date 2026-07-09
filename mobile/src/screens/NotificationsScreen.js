import React, { useCallback, useEffect, useState } from 'react';
import { View, Text, TouchableOpacity } from 'react-native';
import { Screen, Card, Sub, Button, EmptyState } from '../components/UI';
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
        <Button label="Mark all as read" variant="secondary" onPress={readAll} style={{ marginBottom: 12 }} />
      )}
      {items.length === 0 ? (
        <EmptyState text="You're all caught up 🎉" />
      ) : (
        items.map((n) => (
          <TouchableOpacity key={n.id} onPress={() => readOne(n.id)}>
            <Card style={{ paddingVertical: 12 }}>
              <Text style={{ color: colors.text, fontWeight: '700' }}>{n.title}</Text>
              <Sub style={{ marginTop: 3 }}>{n.message}</Sub>
              <Sub style={{ marginTop: 6, fontSize: 11 }}>
                {n.created_at} · tap to mark read
              </Sub>
            </Card>
          </TouchableOpacity>
        ))
      )}
    </Screen>
  );
}
