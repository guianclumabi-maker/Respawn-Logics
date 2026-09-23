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
import { colors, statusColor, statusBg, statusBorder } from '../theme';

export function Screen({ children, refreshing, onRefresh, scroll = true }) {
  if (!scroll) return <View style={styles.screen}>{children}</View>;
  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={{ paddingHorizontal: 16, paddingTop: 12, paddingBottom: 48 }}
      showsVerticalScrollIndicator={false}
      refreshControl={
        onRefresh ? (
          <RefreshControl refreshing={!!refreshing} onRefresh={onRefresh} tintColor={colors.accent} />
        ) : undefined
      }
    >
      {children}
    </ScrollView>
  );
}

export function BrandHeader({ title, subtitle, rightElement }) {
  return (
    <View style={styles.brandHeaderContainer}>
      <View style={{ flex: 1 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 4 }}>
          <View style={styles.brandLogoBadge}>
            <Text style={styles.brandLogoIcon}>⚡</Text>
          </View>
          <Text style={styles.brandNameText}>RESPAWN LOGICS</Text>
          <View style={styles.liveIndicatorDot} />
        </View>
        {title ? <Text style={styles.brandTitleText}>{title}</Text> : null}
        {subtitle ? <Text style={styles.brandSubtitleText}>{subtitle}</Text> : null}
      </View>
      {rightElement ? <View>{rightElement}</View> : null}
    </View>
  );
}

export function Card({ children, style, accentColor, elevated = false }) {
  return (
    <View style={[styles.card, elevated && styles.cardElevated, accentColor && { borderTopWidth: 3, borderTopColor: accentColor }, style]}>
      {children}
    </View>
  );
}

export function MetricCard({ label, value, subtext, icon, accentColor = colors.accent, style }) {
  return (
    <Card style={[{ flex: 1, padding: 14 }, style]} accentColor={accentColor}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
        <Text style={styles.metricLabel}>{label}</Text>
        {icon ? <Text style={{ fontSize: 16 }}>{icon}</Text> : null}
      </View>
      <Text style={styles.metricValue}>{value ?? '—'}</Text>
      {subtext ? <Text style={styles.metricSubtext}>{subtext}</Text> : null}
    </Card>
  );
}

export function Title({ children, style }) {
  return <Text style={[styles.title, style]}>{children}</Text>;
}

export function Sub({ children, style }) {
  return <Text style={[styles.sub, style]}>{children}</Text>;
}

export function Button({ label, onPress, variant = 'primary', disabled, loading, style, icon }) {
  let bg = colors.accent;
  let textColor = '#020617';
  let borderWidth = 0;
  let borderColor = 'transparent';

  if (variant === 'danger') {
    bg = colors.danger;
    textColor = '#FFFFFF';
  } else if (variant === 'secondary' || variant === 'outline') {
    bg = colors.cardAlt;
    textColor = colors.text;
    borderWidth = 1;
    borderColor = colors.border;
  } else if (variant === 'ghost') {
    bg = 'transparent';
    textColor = colors.accent;
  }

  return (
    <TouchableOpacity
      onPress={onPress}
      disabled={disabled || loading}
      activeOpacity={0.8}
      style={[
        styles.button,
        {
          backgroundColor: bg,
          borderWidth,
          borderColor,
          opacity: disabled || loading ? 0.55 : 1,
        },
        style,
      ]}
    >
      {loading ? (
        <ActivityIndicator color={textColor} />
      ) : (
        <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'center' }}>
          {icon ? <Text style={{ marginRight: 6, fontSize: 16 }}>{icon}</Text> : null}
          <Text style={[styles.buttonText, { color: textColor }]}>{label}</Text>
        </View>
      )}
    </TouchableOpacity>
  );
}

export function Field({ label, ...props }) {
  return (
    <View style={{ marginBottom: 14 }}>
      {label ? <Text style={styles.fieldLabel}>{label}</Text> : null}
      <TextInput
        placeholderTextColor={colors.subMuted}
        style={styles.input}
        autoCapitalize="none"
        {...props}
      />
    </View>
  );
}

export function Chip({ label, status, color, style }) {
  const c = color || (status ? statusColor(status) : colors.sub);
  const bg = status ? statusBg(status) : colors.cardAlt;
  const bColor = status ? statusBorder(status) : colors.border;

  return (
    <View style={[styles.chip, { backgroundColor: bg, borderColor: bColor }, style]}>
      <Text style={{ color: c, fontSize: 11, fontWeight: '700', textTransform: 'uppercase', letterSpacing: 0.5 }}>
        {label}
      </Text>
    </View>
  );
}

export function Row({ children, style }) {
  return <View style={[{ flexDirection: 'row', alignItems: 'center' }, style]}>{children}</View>;
}

export function EmptyState({ text, icon = '📂' }) {
  return (
    <Card style={{ alignItems: 'center', paddingVertical: 32 }}>
      <Text style={{ fontSize: 32, marginBottom: 8 }}>{icon}</Text>
      <Text style={{ color: colors.sub, fontSize: 14, textAlign: 'center' }}>{text}</Text>
    </Card>
  );
}

export function LoadingView() {
  return (
    <View style={[styles.screen, { alignItems: 'center', justifyContent: 'center' }]}>
      <ActivityIndicator size="large" color={colors.accent} />
      <Text style={{ color: colors.sub, marginTop: 12, fontSize: 13, fontWeight: '600', letterSpacing: 0.5 }}>
        LOADING RESPAWN LOGICS...
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: colors.bg,
  },
  brandHeaderContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 20,
    marginTop: 8,
    paddingBottom: 14,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  brandLogoBadge: {
    width: 24,
    height: 24,
    borderRadius: 6,
    backgroundColor: 'rgba(16, 185, 129, 0.15)',
    borderWidth: 1,
    borderColor: 'rgba(16, 185, 129, 0.3)',
    alignItems: 'center',
    justify: 'center',
    marginRight: 8,
  },
  brandLogoIcon: {
    fontSize: 12,
  },
  brandNameText: {
    color: colors.accent,
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 1.2,
  },
  liveIndicatorDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: colors.accent,
    marginLeft: 6,
  },
  brandTitleText: {
    color: colors.text,
    fontSize: 22,
    fontWeight: '800',
    marginTop: 2,
  },
  brandSubtitleText: {
    color: colors.sub,
    fontSize: 13,
    marginTop: 2,
  },
  card: {
    backgroundColor: colors.card,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: colors.border,
    padding: 18,
    marginBottom: 14,
  },
  cardElevated: {
    backgroundColor: colors.cardElevated,
    borderColor: 'rgba(255, 255, 255, 0.12)',
  },
  metricLabel: {
    color: colors.sub,
    fontSize: 12,
    fontWeight: '600',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  metricValue: {
    color: colors.text,
    fontSize: 22,
    fontWeight: '800',
  },
  metricSubtext: {
    color: colors.subMuted,
    fontSize: 11,
    marginTop: 4,
  },
  title: {
    color: colors.text,
    fontSize: 18,
    fontWeight: '700',
    marginBottom: 6,
  },
  sub: {
    color: colors.sub,
    fontSize: 13,
  },
  button: {
    borderRadius: 12,
    paddingVertical: 14,
    paddingHorizontal: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonText: {
    fontWeight: '700',
    fontSize: 15,
    letterSpacing: 0.3,
  },
  fieldLabel: {
    color: colors.textSecondary,
    fontSize: 13,
    fontWeight: '600',
    marginBottom: 6,
  },
  input: {
    backgroundColor: colors.cardAlt,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 13,
    color: colors.text,
    fontSize: 15,
  },
  chip: {
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 5,
    alignSelf: 'flex-start',
  },
});
