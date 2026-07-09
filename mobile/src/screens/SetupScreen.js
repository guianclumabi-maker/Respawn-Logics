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
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <Screen>
        <View style={{ alignItems: 'center', marginTop: 60, marginBottom: 30 }}>
          <Text style={{ color: colors.text, fontSize: 30, fontWeight: '800' }}>Respawn Logics</Text>
          <Sub style={{ marginTop: 6 }}>Employee self-service · Basic access</Sub>
        </View>
        <Card>
          <Title>Connect to your server</Title>
          <Sub style={{ marginBottom: 14 }}>
            Enter the address of your Respawn Logics installation. On a local XAMPP setup this is
            your PC's LAN IP, e.g. http://192.168.1.100/respawn-logics
          </Sub>
          <Field
            label="Server URL"
            value={url}
            onChangeText={setUrl}
            keyboardType="url"
            autoCorrect={false}
          />
          <Button label="Connect" onPress={connect} loading={busy} />
        </Card>
      </Screen>
    </KeyboardAvoidingView>
  );
}
