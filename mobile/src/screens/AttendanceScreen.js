import React, { useCallback, useEffect, useState } from 'react';
import { View, Text, Alert } from 'react-native';
import { Screen, Card, Title, Sub, Button, Row, Chip, EmptyState, BrandHeader } from '../components/UI';
import { colors, statusColor } from '../theme';
import * as api from '../api';

function fmtDate(dt) {
  if (!dt) return '—';
  const d = new Date(String(dt).replace(' ', 'T'));
  if (isNaN(d)) return dt;
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', weekday: 'short' });
}

function fmtTime(dt) {
  if (!dt) return '—';
  const d = new Date(String(dt).replace(' ', 'T'));
  if (isNaN(d)) return dt;
  return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
}

function hoursBetween(a, b) {
  if (!a || !b) return null;
  const d1 = new Date(String(a).replace(' ', 'T'));
  const d2 = new Date(String(b).replace(' ', 'T'));
  if (isNaN(d1) || isNaN(d2)) return null;
  return ((d2 - d1) / 3600000).toFixed(1);
}

export default function AttendanceScreen() {
  const [state, setState] = useState(null);
  const [logs, setLogs] = useState([]);
  const [refreshing, setRefreshing] = useState(false);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const [s, t] = await Promise.all([api.getAttendanceStatus(), api.getTimesheet()]);
      if (s.data && s.data.success) setState(s.data.data.state);
      if (t.data && t.data.success) setLogs(t.data.data || []);
    } catch (e) {
      // ignore transient errors
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

  const doClock = async (isOut) => {
    setBusy(true);
    try {
      const { data } = await (isOut ? api.clockOut() : api.clockIn());
      Alert.alert('Attendance', (data && (data.message || data.error)) || 'Done');
      await load();
    } catch (e) {
      Alert.alert('Attendance', e.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen refreshing={refreshing} onRefresh={onRefresh}>
      <BrandHeader title="Time & Attendance" subtitle="Track shift punches and recent timesheets" />

      <Card accentColor={colors.accent}>
        <Row style={{ justifyContent: 'space-between', alignItems: 'center' }}>
          <View>
            <Title style={{ fontSize: 18 }}>Shift Status</Title>
            <Sub style={{ marginTop: 2 }}>
              {state === 'in'
                ? 'Active shift in progress.'
                : state === 'completed'
                ? 'Shift completed for today.'
                : 'Not currently clocked in.'}
            </Sub>
          </View>
          <Chip
            label={state === 'in' ? 'CLOCKED IN' : state === 'completed' ? 'FINISHED' : 'OFF'}
            status={state === 'in' ? 'present' : state === 'completed' ? 'resolved' : 'pending'}
          />
        </Row>

        {state !== 'completed' && (
          <Button
            style={{ marginTop: 14 }}
            label={state === 'in' ? 'Clock Out' : 'Clock In'}
            variant={state === 'in' ? 'danger' : 'primary'}
            icon={state === 'in' ? '🛑' : '⏱️'}
            onPress={() => doClock(state === 'in')}
            loading={busy}
          />
        )}
      </Card>

      <Title style={{ marginTop: 12, marginBottom: 10, fontSize: 16 }}>Recent Timesheet Log</Title>
      {logs.length === 0 ? (
        <EmptyState text="No attendance records found." icon="⏱️" />
      ) : (
        logs.map((log) => {
          const hrs = hoursBetween(log.time_in, log.time_out);
          return (
            <Card key={log.id} style={{ paddingVertical: 14 }}>
              <Row style={{ justifyContent: 'space-between' }}>
                <View style={{ flex: 1 }}>
                  <Text style={{ color: colors.text, fontWeight: '700', fontSize: 15 }}>
                    {fmtDate(log.time_in)}
                  </Text>
                  <Sub style={{ marginTop: 4 }}>
                    {fmtTime(log.time_in)} → {fmtTime(log.time_out)}
                    {hrs ? `  •  ${hrs} hours` : ''}
                  </Sub>
                </View>
                <Chip label={log.status || 'PUNCH'} status={log.status} />
              </Row>
            </Card>
          );
        })
      )}
    </Screen>
  );
}
