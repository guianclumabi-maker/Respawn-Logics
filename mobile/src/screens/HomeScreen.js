import React, { useCallback, useEffect, useState } from 'react';
import { View, Text, Alert, TouchableOpacity } from 'react-native';
import { Screen, Card, MetricCard, Title, Sub, Button, Row, Chip, BrandHeader } from '../components/UI';
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
      <BrandHeader
        title={`Hi${firstName ? `, ${firstName}` : ''} 👋`}
        subtitle={user?.job_title || 'Employee Self-Service'}
        rightElement={
          <View style={{ alignItems: 'flex-end' }}>
            <Chip label={user?.department || 'Employee'} color={colors.accent} />
          </View>
        }
      />

      {/* Primary Action Card: Time Clock */}
      <Card accentColor={colors.accent}>
        <Row style={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
          <View style={{ flex: 1, paddingRight: 10 }}>
            <Title style={{ fontSize: 19 }}>Shift Time Clock</Title>
            <Sub style={{ marginTop: 2 }}>
              {attState === 'in'
                ? `Clocked in at ${stats?.clock_time ?? '—'}`
                : attState === 'completed'
                ? 'Shift completed for today'
                : 'Ready to start your work shift'}
            </Sub>
          </View>
          <Chip
            label={attState === 'in' ? 'ON THE CLOCK' : attState === 'completed' ? 'DONE' : 'OFF DUTY'}
            status={attState === 'in' ? 'present' : attState === 'completed' ? 'resolved' : 'pending'}
          />
        </Row>

        {attState !== 'completed' && (
          <Button
            style={{ marginTop: 16 }}
            label={attState === 'in' ? 'Clock Out Now' : 'Clock In Now'}
            variant={attState === 'in' ? 'danger' : 'primary'}
            icon={attState === 'in' ? '🛑' : '⏱️'}
            onPress={doClock}
            loading={clockBusy}
          />
        )}
      </Card>

      {/* Metrics Row */}
      <View style={{ flexDirection: 'row', gap: 10, marginBottom: 14 }}>
        <MetricCard
          label="Weekly Hours"
          value={stats ? `${Number(stats.total_hours).toFixed(1)}h` : '—'}
          icon="⏱️"
          accentColor={colors.accent}
        />
        <MetricCard
          label="Pending Leaves"
          value={stats ? stats.pending_leaves : '—'}
          icon="🌴"
          accentColor={colors.purple}
        />
        <MetricCard
          label="Open Tasks"
          value={stats ? stats.active_tasks_count : '—'}
          icon="✅"
          accentColor={colors.info}
        />
      </View>

      {/* Task List */}
      <Card>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
          <Title style={{ marginBottom: 0 }}>My Action Items</Title>
          <Chip label={`${stats?.todo_list?.length || 0} Tasks`} color={colors.sub} />
        </View>

        {!stats || !stats.todo_list || stats.todo_list.length === 0 ? (
          <Sub style={{ textAlign: 'center', paddingVertical: 16 }}>No pending action items for today.</Sub>
        ) : (
          stats.todo_list.map((t, idx) => {
            const isDone = Number(t.is_completed);
            return (
              <TouchableOpacity
                key={t.id}
                onPress={() => toggle(t.id)}
                activeOpacity={0.7}
                style={{
                  flexDirection: 'row',
                  alignItems: 'center',
                  paddingVertical: 12,
                  borderBottomWidth: idx === stats.todo_list.length - 1 ? 0 : 1,
                  borderBottomColor: colors.border,
                }}
              >
                <View
                  style={{
                    width: 22,
                    height: 22,
                    borderRadius: 6,
                    borderWidth: isDone ? 0 : 1.5,
                    borderColor: colors.subMuted,
                    backgroundColor: isDone ? colors.accent : 'transparent',
                    alignItems: 'center',
                    justifyContent: 'center',
                    marginRight: 12,
                  }}
                >
                  {isDone ? <Text style={{ color: '#020617', fontSize: 13, fontWeight: '900' }}>✓</Text> : null}
                </View>

                <View style={{ flex: 1 }}>
                  <Text
                    style={{
                      color: isDone ? colors.subMuted : colors.text,
                      fontSize: 15,
                      fontWeight: '600',
                      textDecorationLine: isDone ? 'line-through' : 'none',
                    }}
                  >
                    {t.task_name}
                  </Text>
                  {t.task_description ? <Sub style={{ marginTop: 2, fontSize: 12 }}>{t.task_description}</Sub> : null}
                </View>
              </TouchableOpacity>
            );
          })
        )}
      </Card>
    </Screen>
  );
}
