// Respawn Logics Mobile — Slate Dark Theme (Matches Web App V2.0 Design System)

export const colors = {
  // Core Surfaces (Slate-950 base, Slate-900 cards, Slate-800 elevated)
  bg: '#020617',
  card: '#0F172A',
  cardAlt: '#1E293B',
  cardElevated: '#1E293B',
  border: 'rgba(255, 255, 255, 0.08)',
  borderActive: 'rgba(16, 185, 129, 0.4)',
  borderHover: 'rgba(255, 255, 255, 0.16)',

  // Primary Brand Accent (Emerald 500 - Web App Identity)
  accent: '#10B981',
  accentHover: '#059669',
  accentSoft: 'rgba(16, 185, 129, 0.15)',
  accentGlow: 'rgba(16, 185, 129, 0.25)',

  // Text Hierarchy (Slate 50, Slate 400, Slate 500)
  text: '#F8FAFC',
  textSecondary: '#CBD5E1',
  sub: '#94A3B8',
  subMuted: '#64748B',

  // Curated SaaS Status & Accent Colors
  success: '#10B981',     // Emerald 500
  successBg: 'rgba(16, 185, 129, 0.12)',
  successBorder: 'rgba(16, 185, 129, 0.3)',

  danger: '#EF4444',      // Rose 500
  dangerBg: 'rgba(239, 68, 68, 0.12)',
  dangerBorder: 'rgba(239, 68, 68, 0.3)',

  warning: '#F59E0B',     // Amber 500
  warningBg: 'rgba(245, 158, 11, 0.12)',
  warningBorder: 'rgba(245, 158, 11, 0.3)',

  info: '#06B6D4',        // Cyan 500
  infoBg: 'rgba(6, 182, 212, 0.12)',
  infoBorder: 'rgba(6, 182, 212, 0.3)',

  purple: '#8B5CF6',      // Violet 500
  purpleBg: 'rgba(139, 92, 246, 0.12)',
  purpleBorder: 'rgba(139, 92, 246, 0.3)',
};

export function statusColor(status) {
  const s = String(status || '').toLowerCase();
  if (['approved', 'present', 'on time', 'resolved', 'completed', 'active'].includes(s)) return colors.success;
  if (['rejected', 'late', 'absent', 'terminated', 'cancelled'].includes(s)) return colors.danger;
  if (['pending', 'resolution pending', 'review', 'investigation', 'on leave'].includes(s)) return colors.warning;
  return colors.info;
}

export function statusBg(status) {
  const s = String(status || '').toLowerCase();
  if (['approved', 'present', 'on time', 'resolved', 'completed', 'active'].includes(s)) return colors.successBg;
  if (['rejected', 'late', 'absent', 'terminated', 'cancelled'].includes(s)) return colors.dangerBg;
  if (['pending', 'resolution pending', 'review', 'investigation', 'on leave'].includes(s)) return colors.warningBg;
  return colors.infoBg;
}

export function statusBorder(status) {
  const s = String(status || '').toLowerCase();
  if (['approved', 'present', 'on time', 'resolved', 'completed', 'active'].includes(s)) return colors.successBorder;
  if (['rejected', 'late', 'absent', 'terminated', 'cancelled'].includes(s)) return colors.dangerBorder;
  if (['pending', 'resolution pending', 'review', 'investigation', 'on leave'].includes(s)) return colors.warningBorder;
  return colors.infoBorder;
}
