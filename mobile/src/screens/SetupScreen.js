import React, { useState } from 'react';
import { View, Text, Alert, KeyboardAvoidingView, Platform } from 'react-native';
import { Screen, Card, Title, Sub, Field, Button } from '../components/UI';
import { colors } from '../theme';
import { probeServer } from '../api';
import { useAuth } from '../AuthContext';

export default function SetupScreen() {
  const { saveServer } = useAuth();
  const [url, setUrl] = useState('http://192.168.1.100/respawn-logics');
  const [busy, setBusy] = useState(false);

  const connect = async () => {
    const clean = url.trim().replace(/\/+$/, '');
    if (!/^https?:\/\//i.test(clean)) {
      Alert.alert('Invalid URL', 'The server URL must start with http:// or https://');
      return;
    }
    setBusy(true);
    try {
      await probeServer(clean);
      await saveServer(clean);
    } catch (e) {
      Alert.alert(
        'Cannot reach server',
        'No Respawn Logics API found at that address.\n\nChecklist:\n• XAMPP Apache is running on the PC\n• Phone and PC are on the same Wi-Fi\n• Use the PC’s LAN IP (run "ipconfig"), not localhost\n• Windows Firewall allows Apache (port 80)'
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: colors.bg }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <Screen>
        <View style={{ alignItems: 'center', marginTop: 50, marginBottom: 32 }}>
          <View
            style={{
              width: 56,
              height: 56,
              borderRadius: 16,
              backgroundColor: 'rgba(16, 185, 129, 0.15)',
              borderWidth: 1,
              borderColor: 'rgba(16, 185, 129, 0.3)',
              alignItems: 'center',
              justifyContent: 'center',
              marginBottom: 14,
            }}
          >
            <Text style={{ fontSize: 26 }}>⚡</Text>
          </View>
          <Text style={{ color: colors.accent, fontSize: 13, fontWeight: '800', letterSpacing: 1.5, marginBottom: 4 }}>
            RESPAWN LOGICS
          </Text>
          <Text style={{ color: colors.text, fontSize: 26, fontWeight: '800' }}>Server Connection</Text>
          <Sub style={{ marginTop: 4, fontSize: 13 }}>Employee Mobile Portal Setup</Sub>
        </View>

        <Card accentColor={colors.accent}>
          <Title style={{ fontSize: 18 }}>Connect to HRIS Host</Title>
          <Sub style={{ marginBottom: 14, lineHeight: 18 }}>
            Enter your Respawn Logics server URL. On a local XAMPP setup, use your host PC's local LAN IP address.
          </Sub>
          <Field
            label="Server API URL"
            value={url}
            onChangeText={setUrl}
            keyboardType="url"
            autoCorrect={false}
            placeholder="http://192.168.1.100/respawn-logics"
          />
          <Button label="Test & Connect Host" onPress={connect} loading={busy} icon="📡" style={{ marginTop: 4 }} />
        </Card>
      </Screen>
    </KeyboardAvoidingView>
  );
}
