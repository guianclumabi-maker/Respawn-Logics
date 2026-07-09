import React from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  StyleSheet,
  ScrollView,
  RefreshControl,
} from 'react-native';
import { colors } from '../theme';

export function Screen({ children, refreshing, onRefresh, scroll = true }) {
  if (!scroll) return <View style={styles.screen}>{children}</View>;
  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
      refreshControl={
        onRefresh ? (
          <RefreshControl refreshing={!!refreshing} onRefresh={onRefresh} tintColor={colors.sub} />
        ) : undefined
      }
    >
      {children}
    </ScrollView>
  );
}

export function Card({ children, style }) {
  return <View style={[styles.card, style]}>{children}</View>;
}

export function Title({ children, style }) {
  return <Text style={[styles.title, style]}>{children}</Text>;
}

export function Sub({ children, style }) {
  return <Text style={[styles.sub, style]}>{children}</Text>;
}

export function Button({ label, onPress, variant = 'primary', disabled, loading, style }) {
  const bg =
    variant === 'primary' ? colors.accent : variant === 'danger' ? '#7F1D1D' : colors.cardAlt;
  return (
    <TouchableOpacity
      onPress={onPress}
      disabled={disabled || loading}
      style={[styles.button, { backgroundColor: bg, opacity: disabled || loading ? 0.55 : 1 }, style]}
    >
      {loading ? (
        <ActivityIndicator color="#fff" />
      ) : (
        <Text style={styles.buttonText}>{label}</Text>
      )}
    </TouchableOpacity>
  );
}

export function Field({ label, ...props }) {
  return (
    <View style={{ marginBottom: 12 }}>
      {label ? <Text style={styles.fieldLabel}>{label}</Text> : null}
      <TextInput
        placeholderTextColor={colors.sub}
        style={styles.input}
        autoCapitalize="none"
        {...props}
      />
    </View>
  );
}

export function Chip({ label, color }) {
  return (
    <View style={[styles.chip, { borderColor: color || colors.border }]}>
      <Text style={{ color: color || colors.sub, fontSize: 12, fontWeight: '600' }}>{label}</Text>
    </View>
  );
}

export function Row({ children, style }) {
  return <View style={[{ flexDirection: 'row', alignItems: 'center' }, style]}>{children}</View>;
}

export function EmptyState({ text }) {
  return (
    <Card style={{ alignItems: 'center', paddingVertical: 28 }}>
      <Text style={{ color: colors.sub }}>{text}</Text>
    </Card>
  );
}

export function LoadingView() {
  return (
    <View style={[styles.screen, { alignItems: 'center', justifyContent: 'center' }]}>
      <ActivityIndicator size="large" color={colors.accent} />
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  card: {
    backgroundColor: colors.card,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.border,
    padding: 16,
    marginBottom: 12,
  },
  title: { color: colors.text, fontSize: 18, fontWeight: '700', marginBottom: 6 },
  sub: { color: colors.sub, fontSize: 13 },
  button: {
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonText: { color: '#fff', fontWeight: '700', fontSize: 15 },
  fieldLabel: { color: colors.sub, fontSize: 13, marginBottom: 6 },
  input: {
    backgroundColor: colors.cardAlt,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 12,
    color: colors.text,
    fontSize: 15,
  },
  chip: {
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 4,
    alignSelf: 'flex-start',
  },
});
