import React, { useCallback, useEffect, useState } from 'react';
import { View, Text, Alert, TouchableOpacity } from 'react-native';
import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import { Screen, Card, Title, Sub, Row, EmptyState } from '../components/UI';
import { colors } from '../theme';
import * as api from '../api';

function money(v) {
  const n = Number(v);
  if (isNaN(n)) return v ?? '—';
  return '₱' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function PayslipsScreen() {
  const [slips, setSlips] = useState([]);
  const [refreshing, setRefreshing] = useState(false);
  const [downloading, setDownloading] = useState(null);

  const load = useCallback(async () => {
    try {
      const { data } = await api.getMyPayslips();
      if (data && data.success) setSlips(data.data || []);
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

  const download = async (slip) => {
    setDownloading(slip.id);
    try {
      const url = api.payslipPdfUrl(slip.id);
      const dest = FileSystem.cacheDirectory + `Payslip_${slip.id}.pdf`;
      const res = await FileSystem.downloadAsync(url, dest);
      if (res.status !== 200) {
        throw new Error('The PDF is not available yet (it may not have been generated).');
      }
      if (await Sharing.isAvailableAsync()) {
        await Sharing.shareAsync(res.uri, {
          mimeType: 'application/pdf',
          dialogTitle: `Payslip ${slip.id}`,
        });
      } else {
        Alert.alert('Downloaded', 'Saved to app cache: ' + res.uri);
      }
    } catch (e) {
      Alert.alert('Payslip', e.message);
    } finally {
      setDownloading(null);
    }
  };

  return (
    <Screen refreshing={refreshing} onRefresh={onRefresh}>
      <Title style={{ marginBottom: 10 }}>My payslips</Title>
      {slips.length === 0 ? (
        <EmptyState text="No payslips available yet." />
      ) : (
        slips.map((s) => (
          <TouchableOpacity key={s.id} onPress={() => download(s)} disabled={downloading === s.id}>
            <Card style={{ paddingVertical: 14 }}>
              <Row style={{ justifyContent: 'space-between' }}>
                <View style={{ flex: 1 }}>
                  <Text style={{ color: colors.text, fontWeight: '700' }}>
                    {s.payroll_period_start} → {s.payroll_period_end}
                  </Text>
                  <Sub style={{ marginTop: 2 }}>Pay date: {s.pay_date}</Sub>
                </View>
                <View style={{ alignItems: 'flex-end' }}>
                  {s.net_pay !== undefined && (
                    <Text style={{ color: colors.success, fontWeight: '800' }}>
                      {money(s.net_pay)}
                    </Text>
                  )}
                  <Sub style={{ marginTop: 2 }}>
                    {downloading === s.id ? 'Downloading…' : 'Tap for PDF'}
                  </Sub>
                </View>
              </Row>
            </Card>
          </TouchableOpacity>
        ))
      )}
      <Sub style={{ textAlign: 'center', marginTop: 6 }}>
        Tap a payslip to download and share its PDF.
      </Sub>
    </Screen>
  );
}
