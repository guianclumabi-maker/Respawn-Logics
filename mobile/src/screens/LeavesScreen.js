import React, { useCallback, useEffect, useState } from 'react';
import { View, Text, Alert, TouchableOpacity } from 'react-native';
import { Screen, Card, Title, Sub, Button, Row, Chip, Field, EmptyState, BrandHeader } from '../components/UI';
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
      <BrandHeader title="Leave Management" subtitle="Track PTO allowances and submit time-off requests" />

      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }}>
        <Title style={{ fontSize: 16, marginBottom: 0 }}>Leave Balances</Title>
        <Button
          label={showForm ? 'Cancel' : '+ Request Leave'}
          variant={showForm ? 'secondary' : 'primary'}
          onPress={() => setShowForm(!showForm)}
          style={{ paddingVertical: 8, paddingHorizontal: 12 }}
        />
      </View>

      {balances.length === 0 ? (
        <EmptyState text="No leave balances configured." icon="🌴" />
      ) : (
        <Row style={{ flexWrap: 'wrap', gap: 10, marginBottom: 14 }}>
          {balances.map((b) => {
            const left = Number(b.total_allowance) - Number(b.used_balance);
            return (
              <Card key={b.leave_type} style={{ flexGrow: 1, minWidth: '45%', marginBottom: 4 }} accentColor={colors.purple}>
                <Sub style={{ color: colors.sub, fontSize: 12, fontWeight: '700', textTransform: 'uppercase' }}>
                  {b.leave_type}
                </Sub>
                <Text style={{ color: colors.text, fontSize: 24, fontWeight: '800', marginTop: 4 }}>
                  {left}
                  <Text style={{ color: colors.subMuted, fontSize: 13, fontWeight: '500' }}>
                    {' '}
                    / {Number(b.total_allowance)} days
                  </Text>
                </Text>
              </Card>
            );
          })}
        </Row>
      )}

      {showForm && (
        <Card accentColor={colors.accent}>
          <Title style={{ fontSize: 17 }}>New Time-Off Request</Title>
          <Sub style={{ marginBottom: 10 }}>Select Leave Category</Sub>
          <Row style={{ flexWrap: 'wrap', gap: 8, marginBottom: 14 }}>
            {balances.map((b) => {
              const isSelected = leaveType === b.leave_type;
              return (
                <TouchableOpacity key={b.leave_type} onPress={() => setLeaveType(b.leave_type)}>
                  <Chip
                    label={b.leave_type}
                    color={isSelected ? colors.accent : colors.sub}
                    style={isSelected ? { backgroundColor: colors.accentSoft, borderColor: colors.accent } : null}
                  />
                </TouchableOpacity>
              );
            })}
          </Row>
          <Field
            label="Start Date (YYYY-MM-DD)"
            value={startDate}
            onChangeText={setStartDate}
            placeholder="2026-07-15"
          />
          <Field
            label="End Date (YYYY-MM-DD)"
            value={endDate}
            onChangeText={setEndDate}
            placeholder="2026-07-16"
          />
          <Field label="Reason (Optional)" value={reason} onChangeText={setReason} multiline />
          <Button label="Submit Time-Off Request" onPress={submit} loading={busy} icon="🚀" />
        </Card>
      )}

      <Title style={{ marginTop: 8, marginBottom: 10, fontSize: 16 }}>Request History</Title>
      {requests.length === 0 ? (
        <EmptyState text="No time-off requests submitted yet." icon="📜" />
      ) : (
        requests.map((r) => (
          <Card key={r.id} style={{ paddingVertical: 14 }}>
            <Row style={{ justifyContent: 'space-between' }}>
              <View style={{ flex: 1, paddingRight: 10 }}>
                <Text style={{ color: colors.text, fontWeight: '700', fontSize: 15 }}>{r.leave_type}</Text>
                <Sub style={{ marginTop: 4, color: colors.subSecondary }}>
                  📅 {r.start_date} → {r.end_date}
                </Sub>
                {r.reason ? <Sub style={{ marginTop: 2, fontStyle: 'italic' }}>"{r.reason}"</Sub> : null}
              </View>
              <Chip label={r.status} status={r.status} />
            </Row>
          </Card>
        ))
      )}
    </Screen>
  );
}
