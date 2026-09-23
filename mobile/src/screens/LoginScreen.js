import React, { useState } from 'react';
import { View, Text, Alert, KeyboardAvoidingView, Platform, TouchableOpacity } from 'react-native';
import { Screen, Card, Title, Sub, Field, Button, Chip } from '../components/UI';
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
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: colors.bg }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <Screen>
        {/* Brand Header Badge */}
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
          <Text style={{ color: colors.text, fontSize: 28, fontWeight: '800' }}>Welcome Back</Text>
          <Sub style={{ marginTop: 6, fontSize: 13 }}>Connected to {serverUrl}</Sub>
        </View>

        <Card accentColor={colors.accent}>
          <Title style={{ fontSize: 18, marginBottom: 16 }}>Sign In to Account</Title>
          <Field
            label="Work Email Address"
            value={email}
            onChangeText={setEmail}
            keyboardType="email-address"
            autoComplete="email"
            placeholder="name@company.com"
          />
          <Field
            label="Password"
            value={password}
            onChangeText={setPassword}
            secureTextEntry
            placeholder="••••••••"
          />
          <Button label="Sign In" onPress={submit} loading={busy} icon="🔐" style={{ marginTop: 6 }} />
          
          <TouchableOpacity onPress={changeServer} style={{ marginTop: 18, alignItems: 'center' }}>
            <Text style={{ color: colors.accent, fontSize: 13, fontWeight: '600' }}>Switch Server Host</Text>
          </TouchableOpacity>
        </Card>

        <Sub style={{ textAlign: 'center', marginTop: 12, paddingHorizontal: 20, fontSize: 12, color: colors.subMuted }}>
          Note: Accounts requiring 2FA or initial password reset must access via the desktop web application.
        </Sub>
      </Screen>
    </KeyboardAvoidingView>
  );
}
