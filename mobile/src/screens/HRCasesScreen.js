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
        label={showForm ? 'Cancel' : '+ File New HR Case'}
        variant={showForm ? 'secondary' : 'primary'}
        onPress={() => setShowForm(!showForm)}
        style={{ marginBottom: 14 }}
      />

      {showForm && (
        <Card accentColor={colors.purple}>
          <Title style={{ fontSize: 17 }}>New HR Case Filing</Title>
          <Field
            label="Case Title & Details"
            value={name}
            onChangeText={setName}
            placeholder="Provide details regarding the case…"
            multiline
          />
          <Button label="Submit HR Case" onPress={submit} loading={busy} icon="📝" />
        </Card>
      )}

      {cases.length === 0 ? (
        <EmptyState text="No HR cases currently on file." icon="🗂️" />
      ) : (
        cases.map((c) => (
          <TouchableOpacity key={c.id} onLongPress={() => advance(c)} activeOpacity={0.8}>
            <Card style={{ paddingVertical: 14 }} accentColor={colors.purple}>
              <Row style={{ justifyContent: 'space-between', alignItems: 'center' }}>
                <View style={{ flex: 1, paddingRight: 10 }}>
                  <Text style={{ color: colors.text, fontWeight: '800', fontSize: 15 }}>{c.name}</Text>
                  <Sub style={{ marginTop: 4 }}>Filed: {c.applied}</Sub>
                </View>
                <Chip label={c.stage} status={c.stage} />
              </Row>
            </Card>
          </TouchableOpacity>
        ))
      )}
      {cases.length > 0 && (
        <Sub style={{ textAlign: 'center', marginTop: 8, fontSize: 12 }}>
          Tip: Long-press a case card to advance its resolution stage.
        </Sub>
      )}
    </Screen>
  );
}
