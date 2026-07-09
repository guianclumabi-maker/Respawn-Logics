import React, { useState } from 'react';
import { View, Text, Alert, KeyboardAvoidingView, Platform, TouchableOpacity } from 'react-native';
import { Screen, Card, Title, Sub, Field, Button } from '../components/UI';
import { colors } from '../theme';
import { useAuth } from '../AuthContext';

export default function LoginScreen() {
  const { signIn, serverUrl, changeServer } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    if (!email.trim() || !password) {
      Alert.alert('Missing details', 'Please enter your email and password.');
      return;
    }
    setBusy(true);
    try {
      await signIn(email.trim(), password);
    } catch (e) {
      Alert.alert('Sign in failed', e.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <Screen>
        <View style={{ alignItems: 'center', marginTop: 60, marginBottom: 30 }}>
          <Text style={{ color: colors.text, fontSize: 30, fontWeight: '800' }}>Welcome back</Text>
          <Sub style={{ marginTop: 6 }}>{serverUrl}</Sub>
        </View>
        <Card>
          <Title>Sign in</Title>
          <Field
            label="Email"
            value={email}
            onChangeText={setEmail}
            keyboardType="email-address"
            autoComplete="email"
          />
          <Field
            label="Password"
            value={password}
            onChangeText={setPassword}
            secureTextEntry
          />
          <Button label="Sign in" onPress={submit} loading={busy} />
          <TouchableOpacity onPress={changeServer} style={{ marginTop: 16, alignItems: 'center' }}>
            <Text style={{ color: colors.accent, fontSize: 13 }}>Use a different server</Text>
          </TouchableOpacity>
        </Card>
        <Sub style={{ textAlign: 'center', marginTop: 8 }}>
          Accounts with 2FA or a pending password change must use the web app.
        </Sub>
      </Screen>
    </KeyboardAvoidingView>
  );
}
