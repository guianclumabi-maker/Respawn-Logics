import React, { useCallback, useEffect, useState } from 'react';
import { View, Text, Alert, TouchableOpacity } from 'react-native';
import { Screen, Card, Title, Sub, Button, Row, Chip, Field, EmptyState } from '../components/UI';
import { colors, statusColor } from '../theme';
import * as api from '../api';

const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;

export default function LeavesScreen() {
  const [balances, setBalances] = useState([]);
  const [requests, setRequests] = useState([]);
  const [refreshing, setRefreshing] = useState(false);

  // apply form
  const [showForm, setShowForm] = useState(false);
  const [leaveType, setLeaveType] = useState('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const [b, r] = await Promise.all([api.getLeaveBalances(), api.getMyLeaveRequests()]);
      if (b.data && b.data.success) {
        setBalances(b.data.data || []);
        if (!leaveType && b.data.data && b.data.data.length > 0) {
          setLeaveType(b.data.data[0].leave_type);
        }
      }
      if (r.data && r.data.success) setRequests(r.data.data || []);
    } catch (e) {
      // ignore transient errors
    }
  }, [leaveType]);

  useEffect(() => {
    load();
  }, [load]);

  const onRefresh = async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  };

  const submit = async () => {
    if (!leaveType) return Alert.alert('Leave request', 'Pick a leave type.');
    if (!DATE_RE.test(startDate) || !DATE_RE.test(endDate)) {
      return Alert.alert('Leave request', 'Dates must be in YYYY-MM-DD format.');
    }
    setBusy(true);
    try {
      const { data } = await api.applyLeave({
        leave_type: leaveType,
        start_date: startDate,
        end_date: endDate,
        reason,
      });
      if (data && data.success) {
        Alert.alert('Leave request', 'Your request has been submitted.');
        setShowForm(false);
        setStartDate('');
        setEndDate('');
        setReason('');
        await load();
      } else {
        Alert.alert('Leave request', (data && data.error) || 'Submission failed.');
      }
    } catch (e) {
      Alert.alert('Leave request', e.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen refreshing={refreshing} onRefresh={onRefresh}>
      <Title style={{ marginBottom: 10 }}>Leave balances</Title>
      {balances.length === 0 ? (
        <EmptyState text="No leave balances set up for your account." />
      ) : (
        <Row style={{ flexWrap: 'wrap', gap: 10, marginBottom: 8 }}>
          {balances.map((b) => {
            const left = Number(b.total_allowance) - Number(b.used_balance);
            return (
              <Card key={b.leave_type} style={{ flexGrow: 1, minWidth: '45%', marginBottom: 4 }}>
                <Sub>{b.leave_type}</Sub>
                <Text style={{ color: colors.text, fontSize: 22, fontWeight: '800', marginTop: 4 }}>
                  {left}
                  <Text style={{ color: colors.sub, fontSize: 13, fontWeight: '400' }}>
                    {' '}
                    / {Number(b.total_allowance)} days
                  </Text>
                </Text>
              </Card>
            );
          })}
        </Row>
      )}

      <Button
        label={showForm ? 'Cancel' : 'Request leave'}
        variant={showForm ? 'secondary' : 'primary'}
        onPress={() => setShowForm(!showForm)}
        style={{ marginBottom: 12 }}
      />

      {showForm && (
        <Card>
          <Title>New leave request</Title>
          <Sub style={{ marginBottom: 10 }}>Leave type</Sub>
          <Row style={{ flexWrap: 'wrap', gap: 8, marginBottom: 12 }}>
            {balances.map((b) => (
              <TouchableOpacity key={b.leave_type} onPress={() => setLeaveType(b.leave_type)}>
                <Chip
                  label={b.leave_type}
                  color={leaveType === b.leave_type ? colors.accent : colors.sub}
                />
              </TouchableOpacity>
            ))}
          </Row>
          <Field
            label="Start date (YYYY-MM-DD)"
            value={startDate}
            onChangeText={setStartDate}
            placeholder="2026-07-15"
          />
          <Field
            label="End date (YYYY-MM-DD)"
            value={endDate}
            onChangeText={setEndDate}
            placeholder="2026-07-16"
          />
          <Field label="Reason (optional)" value={reason} onChangeText={setReason} multiline />
          <Button label="Submit request" onPress={submit} loading={busy} />
        </Card>
      )}

      <Title style={{ marginTop: 8, marginBottom: 10 }}>My requests</Title>
      {requests.length === 0 ? (
        <EmptyState text="No leave requests yet." />
      ) : (
        requests.map((r) => (
          <Card key={r.id} style={{ paddingVertical: 12 }}>
            <Row style={{ justifyContent: 'space-between' }}>
              <View style={{ flex: 1, paddingRight: 10 }}>
                <Text style={{ color: colors.text, fontWeight: '700' }}>{r.leave_type}</Text>
                <Sub style={{ marginTop: 2 }}>
                  {r.start_date} → {r.end_date}
                </Sub>
                {r.reason ? <Sub style={{ marginTop: 2 }}>{r.reason}</Sub> : null}
              </View>
              <Chip label={r.status} color={statusColor(r.status)} />
            </Row>
          </Card>
        ))
      )}
    </Screen>
  );
}
