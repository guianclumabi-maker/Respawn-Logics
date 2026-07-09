import React, { useCallback, useEffect, useState } from 'react';
import { View, Text, Image } from 'react-native';
import { Screen, Card, Sub, Row, EmptyState } from '../components/UI';
import { colors } from '../theme';
import * as api from '../api';

export default function AnnouncementsScreen() {
  const [posts, setPosts] = useState([]);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    try {
      const { data } = await api.getAnnouncements();
      if (data && data.success) setPosts(data.data || []);
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

  return (
    <Screen refreshing={refreshing} onRefresh={onRefresh}>
      {posts.length === 0 ? (
        <EmptyState text="No announcements yet." />
      ) : (
        posts.map((p) => (
          <Card key={p.id}>
            <Row style={{ marginBottom: 8 }}>
              <View
                style={{
                  width: 38,
                  height: 38,
                  borderRadius: 19,
                  backgroundColor: colors.accentSoft,
                  alignItems: 'center',
                  justifyContent: 'center',
                  marginRight: 10,
                }}
              >
                <Text style={{ color: colors.text, fontWeight: '800' }}>
                  {(p.full_name || '?').charAt(0).toUpperCase()}
                </Text>
              </View>
              <View style={{ flex: 1 }}>
                <Text style={{ color: colors.text, fontWeight: '700' }}>{p.full_name}</Text>
                <Sub>
                  {p.job_title ? `${p.job_title} · ` : ''}
                  {p.created_at}
                </Sub>
              </View>
            </Row>
            {p.caption ? (
              <Text style={{ color: colors.text, lineHeight: 20 }}>{p.caption}</Text>
            ) : null}
            {p.image_url ? (
              <Image
                source={{ uri: api.announcementImageUrl(p.id) }}
                style={{
                  width: '100%',
                  height: 200,
                  borderRadius: 10,
                  marginTop: 10,
                  backgroundColor: colors.cardAlt,
                }}
                resizeMode="cover"
              />
            ) : null}
          </Card>
        ))
      )}
    </Screen>
  );
}
