// Respawn Logics mobile — dark theme (matches the web app's dark preference)
export const colors = {
  bg: '#0B1220',
  card: '#131C2E',
  cardAlt: '#182338',
  border: '#1F2A3D',
  accent: '#6366F1',
  accentSoft: '#312E81',
  text: '#E5EAF3',
  sub: '#8A94A6',
  success: '#34D399',
  danger: '#F87171',
  warning: '#FBBF24',
  info: '#60A5FA',
};

export function statusColor(status) {
  const s = String(status || '').toLowerCase();
  if (['approved', 'present', 'on time', 'resolved', 'completed'].includes(s)) return colors.success;
  if (['rejected', 'late', 'absent'].includes(s)) return colors.danger;
  if (['pending', 'resolution pending', 'review', 'investigation'].includes(s)) return colors.warning;
  return colors.info;
}
