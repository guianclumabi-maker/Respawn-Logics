import React, { useCallback, useEffect, useState } from 'react';
import { View, Text, Alert, TouchableOpacity } from 'react-native';
import { Screen, Card, Title, Sub, Button, Row, Chip, Field, EmptyState } from '../components/UI';
import { colors, statusColor } from '../theme';
import * as api from '../api';

const STAGES = ['Reported', 'Review', 'Investigation', 'Resolution Pending', 'Resolved'];

export default function HRCasesScreen() {
  const [cases, setCases] = useState([]);
  const [refreshing, setRefreshing] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [name, setName] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const { data } = await api.getHrCases();
      if (data && data.success) setCases(data.cases || []);
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

  const submit = async () => {
    if (!name.trim()) return Alert.alert('HR case', 'Please describe the case.');
    setBusy(true);
    try {
      const { data } = await api.addHrCase(name.trim());
      if (data && data.success) {
        setName('');
        setShowForm(false);
        await load();
      } else {
        Alert.alert('HR case', (data && data.error) || 'Failed to file the case.');
      }
    } catch (e) {
      Alert.alert('HR case', e.message);
    } finally {
      setBusy(false);
    }
  };

  const advance = (c) => {
    const idx = STAGES.indexOf(c.stage);
    if (idx < 0 || idx >= STAGES.length - 1) return;
    const next = STAGES[idx + 1];
    Alert.alert('Advance case', `Move "${c.name}" to "${next}"?`, [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Move',
        onPress: async () => {
          await api.updateHrCaseStage(c.id, next);
          await load();
        },
      },
    ]);
  };

  return (
    <Screen refreshing={refreshing} onRefresh={onRefresh}>
      <Button
        label={showForm ? 'Cancel' : 'File a new case'}
        variant={showForm ? 'secondary' : 'primary'}
        onPress={() => setShowForm(!showForm)}
        style={{ marginBottom: 12 }}
      />
      {showForm && (
        <Card>
          <Title>New HR case</Title>
          <Field
            label="Case details"
            value={name}
            onChangeText={setName}
            placeholder="Describe the issue…"
            multiline
          />
          <Button label="Submit case" onPress={submit} loading={busy} />
        </Card>
      )}

      {cases.length === 0 ? (
        <EmptyState text="No HR cases on file." />
      ) : (
        cases.map((c) => (
          <TouchableOpacity key={c.id} onLongPress={() => advance(c)}>
            <Card style={{ paddingVertical: 12 }}>
              <Row style={{ justifyContent: 'space-between' }}>
                <View style={{ flex: 1, paddingRight: 10 }}>
                  <Text style={{ color: colors.text, fontWeight: '700' }}>{c.name}</Text>
                  <Sub style={{ marginTop: 2 }}>Filed {c.applied}</Sub>
                </View>
                <Chip label={c.stage} color={statusColor(c.stage)} />
              </Row>
            </Card>
          </TouchableOpacity>
        ))
      )}
      {cases.length > 0 && (
        <Sub style={{ textAlign: 'center', marginTop: 6 }}>
          Long-press a case to advance it to the next stage.
        </Sub>
      )}
    </Screen>
  );
}
