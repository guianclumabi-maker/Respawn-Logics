import React, { useCallback, useEffect, useState } from 'react';
import { View, Text, Alert } from 'react-native';
import { Screen, Card, Title, Sub, Button, Row, EmptyState, Chip } from '../components/UI';
import { colors } from '../theme';
import { useAuth } from '../AuthContext';
import * as api from '../api';

export default function ApprovalsScreen() {
  const { hasPermission } = useAuth();
  const canLeaves = hasPermission('leave.manage');
  const canAttendance = hasPermission('attendance.manage');

  const [leaves, setLeaves] = useState([]);
  const [timesheets, setTimesheets] = useState([]);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    try {
      const jobs = [];
      if (canLeaves) jobs.push(api.getLeaveApprovals());
      if (canAttendance) jobs.push(api.getAttendanceApprovals());
      const results = await Promise.all(jobs);
      let i = 0;
      if (canLeaves) {
        const r = results[i++];
        if (r.data && r.data.success) setLeaves(r.data.data || []);
      }
      if (canAttendance) {
        const r = results[i++];
        if (r.data && r.data.success) setTimesheets(r.data.data || []);
      }
    } catch (e) {
      // ignore
    }
  }, [canLeaves, canAttendance]);

  useEffect(() => {
    load();
  }, [load]);

  const onRefresh = async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  };

  const decideLeaveReq = async (id, decision) => {
    const { data } = await api.decideLeave(id, decision);
    if (!data || !data.success) {
      Alert.alert('Approval', (data && data.error) || 'Action failed.');
    }
    await load();
  };

  const approveTs = async (id) => {
    const { data } = await api.approveTimesheet(id);
    if (!data || !data.success) {
      Alert.alert('Approval', (data && data.error) || 'Action failed.');
    }
    await load();
  };

  if (!canLeaves && !canAttendance) {
    return (
      <Screen>
        <EmptyState text="You don't have manager approval permissions." icon="🔒" />
      </Screen>
    );
  }

  return (
    <Screen refreshing={refreshing} onRefresh={onRefresh}>
      {canLeaves && (
        <>
          <Title style={{ marginBottom: 10, fontSize: 16 }}>Pending Leave Approvals</Title>
          {leaves.length === 0 ? (
            <EmptyState text="No pending leave requests to review." icon="✅" />
          ) : (
            leaves.map((r) => (
              <Card key={r.id} accentColor={colors.purple}>
                <Row style={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
                  <View style={{ flex: 1 }}>
                    <Text style={{ color: colors.text, fontWeight: '800', fontSize: 15 }}>
                      {r.full_name || r.employee_email}
                    </Text>
                    <Sub style={{ marginTop: 2, color: colors.accent }}>
                      {r.leave_type} · {r.start_date} → {r.end_date}
                    </Sub>
                    {r.reason ? <Sub style={{ marginTop: 4, fontStyle: 'italic' }}>"{r.reason}"</Sub> : null}
                  </View>
                  <Chip label="PENDING" status="pending" />
                </Row>
                <Row style={{ gap: 10, marginTop: 14 }}>
                  <Button
                    label="Approve"
                    style={{ flex: 1 }}
                    icon="✓"
                    onPress={() => decideLeaveReq(r.id, 'Approved')}
                  />
                  <Button
                    label="Reject"
                    variant="danger"
                    style={{ flex: 1 }}
                    icon="✕"
                    onPress={() => decideLeaveReq(r.id, 'Rejected')}
                  />
                </Row>
              </Card>
            ))
          )}
        </>
      )}

      {canAttendance && (
        <>
          <Title style={{ marginTop: 14, marginBottom: 10, fontSize: 16 }}>Timesheet Approvals</Title>
          {timesheets.length === 0 ? (
            <EmptyState text="No timesheets awaiting approval." icon="⏱️" />
          ) : (
            timesheets.map((t) => (
              <Card key={t.id} accentColor={colors.info}>
                <Text style={{ color: colors.text, fontWeight: '800', fontSize: 15 }}>
                  {t.full_name || t.employee_email}
                </Text>
                <Sub style={{ marginTop: 2 }}>
                  Punch: {t.time_in} → {t.time_out || '—'}
                </Sub>
                <Button
                  label="Approve Timesheet"
                  style={{ marginTop: 12 }}
                  icon="✓"
                  onPress={() => approveTs(t.id)}
                />
              </Card>
            ))
          )}
        </>
      )}
    </Screen>
  );
}
