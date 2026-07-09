import React, { useCallback, useEffect, useState } from 'react';
import { View, Text, Alert, TouchableOpacity } from 'react-native';
import { Screen, Card, Title, Sub, Button, Row, Chip } from '../components/UI';
import { colors } from '../theme';
import { useAuth } from '../AuthContext';
import * as api from '../api';

export default function HomeScreen() {
  const { user } = useAuth();
  const [stats, setStats] = useState(null);
  const [attState, setAttState] = useState(null); // 'in' | 'out' | 'completed'
  const [refreshing, setRefreshing] = useState(false);
  const [clockBusy, setClockBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const [s, a] = await Promise.all([api.getDashboardStats(), api.getAttendanceStatus()]);
      if (s.data && s.data.success) setStats(s.data.data);
      if (a.data && a.data.success) setAttState(a.data.data.state);
    } catch (e) {
      // keep previous data on transient failures
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

  const doClock = async () => {
    setClockBusy(true);
    try {
      const fn = attState === 'in' ? api.clockOut : api.clockIn;
      const { data } = await fn();
      if (data && data.success) {
        Alert.alert('Done', data.message || 'Saved.');
      } else {
        Alert.alert('Attendance', (data && data.error) || 'Action failed.');
      }
      await load();
    } catch (e) {
      Alert.alert('Attendance', e.message);
    } finally {
      setClockBusy(false);
    }
  };

  const toggle = async (taskId) => {
    try {
      await api.toggleTask(taskId);
      await load();
    } catch (e) {
      // ignore
    }
  };

  const firstName = (user?.name || '').split(' ')[0];

  return (
    <Screen refreshing={refreshing} onRefresh={onRefresh}>
      <Text style={{ color: colors.text, fontSize: 24, fontWeight: '800', marginBottom: 2 }}>
        Hi{firstName ? `, ${firstName}` : ''} 👋
      </Text>
      <Sub style={{ marginBottom: 16 }}>{user?.job_title || 'Employee self-service'}</Sub>

      <Card>
        <Row style={{ justifyContent: 'space-between' }}>
          <View>
            <Title>Time clock</Title>
            <Sub>
              {attState === 'in'
                ? `Clocked in at ${stats?.clock_time ?? '—'}`
                : attState === 'completed'
                ? 'Shift completed for today'
                : 'Not clocked in yet'}
            </Sub>
          </View>
          <Chip
            label={attState === 'in' ? 'ON THE CLOCK' : attState === 'completed' ? 'DONE' : 'OFF'}
            color={attState === 'in' ? colors.success : attState === 'completed' ? colors.info : colors.sub}
          />
        </Row>
        {attState !== 'completed' && (
          <Button
            style={{ marginTop: 14 }}
            label={attState === 'in' ? 'Clock out' : 'Clock in'}
            variant={attState === 'in' ? 'danger' : 'primary'}
            onPress={doClock}
            loading={clockBusy}
          />
        )}
      </Card>

      <Row style={{ gap: 12 }}>
        <Card style={{ flex: 1 }}>
          <Sub>Hours (7 days)</Sub>
          <Text style={styleStat}>{stats ? Number(stats.total_hours).toFixed(1) : '—'}</Text>
        </Card>
        <Card style={{ flex: 1 }}>
          <Sub>Pending leaves</Sub>
          <Text style={styleStat}>{stats ? stats.pending_leaves : '—'}</Text>
        </Card>
        <Card style={{ flex: 1 }}>
          <Sub>Open tasks</Sub>
          <Text style={styleStat}>{stats ? stats.active_tasks_count : '—'}</Text>
        </Card>
      </Row>

      <Card>
        <Title>My tasks</Title>
        {!stats || !stats.todo_list || stats.todo_list.length === 0 ? (
          <Sub>No tasks yet.</Sub>
        ) : (
          stats.todo_list.map((t) => (
            <TouchableOpacity
              key={t.id}
              onPress={() => toggle(t.id)}
              style={{
                flexDirection: 'row',
                alignItems: 'center',
                paddingVertical: 10,
                borderBottomWidth: 1,
                borderBottomColor: colors.border,
              }}
            >
              <Text style={{ fontSize: 16, marginRight: 10 }}>
                {Number(t.is_completed) ? '✅' : '⬜'}
              </Text>
              <View style={{ flex: 1 }}>
                <Text
                  style={{
                    color: Number(t.is_completed) ? colors.sub : colors.text,
                    textDecorationLine: Number(t.is_completed) ? 'line-through' : 'none',
                  }}
                >
                  {t.task_name}
                </Text>
                {t.task_description ? <Sub>{t.task_description}</Sub> : null}
              </View>
            </TouchableOpacity>
          ))
        )}
      </Card>
    </Screen>
  );
}

const styleStat = {
  color: colors.text,
  fontSize: 22,
  fontWeight: '800',
  marginTop: 4,
};
