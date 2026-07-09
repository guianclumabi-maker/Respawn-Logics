// Lightweight API client for the Respawn Logics PHP backend.
// Talks to the same session-cookie + CSRF API the web SPA uses.
import AsyncStorage from '@react-native-async-storage/async-storage';

const SERVER_KEY = 'rl.serverUrl';

let baseUrl = null;
let csrfToken = null;

export function getBaseUrl() {
  return baseUrl;
}

export async function loadBaseUrl() {
  if (!baseUrl) baseUrl = await AsyncStorage.getItem(SERVER_KEY);
  return baseUrl;
}

export async function setBaseUrl(url) {
  baseUrl = String(url || '').trim().replace(/\/+$/, '');
  await AsyncStorage.setItem(SERVER_KEY, baseUrl);
  return baseUrl;
}

export async function clearBaseUrl() {
  baseUrl = null;
  csrfToken = null;
  await AsyncStorage.removeItem(SERVER_KEY);
}

export function setCsrfToken(token) {
  if (token) csrfToken = token;
}

async function rawRequest(path, { method = 'GET', body, retried = false } = {}) {
  if (!baseUrl) throw new Error('Server URL is not configured yet.');

  const headers = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (method !== 'GET' && csrfToken) headers['X-CSRF-Token'] = csrfToken;

  let res;
  try {
    res = await fetch(`${baseUrl}${path}`, {
      method,
      headers,
      credentials: 'include',
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch (e) {
    throw new Error(
      'Could not reach the server. Make sure your phone is on the same Wi-Fi as the PC and the server URL is correct.'
    );
  }

  let data = null;
  const text = await res.text();
  try {
    data = JSON.parse(text);
  } catch (e) {
    throw new Error(`The server returned an unexpected response (HTTP ${res.status}).`);
  }

  // Auto-recover from a CSRF mismatch once — the API hands back a fresh token.
  if (res.status === 403 && data && data.error === 'CSRF token mismatch' && data.csrf_token && !retried) {
    csrfToken = data.csrf_token;
    return rawRequest(path, { method, body, retried: true });
  }

  return { status: res.status, data };
}

/** Call a modern front-controller route: /api/index.php?route=X&action=Y */
export function apiRoute(route, action, { method = 'GET', body, query = '' } = {}) {
  return rawRequest(`/api/index.php?route=${route}&action=${action}${query}`, { method, body });
}

/** Absolute URL for binary endpoints (payslip PDFs, announcement images). */
export function fileUrl(route, action, query = '') {
  return `${baseUrl}/api/index.php?route=${route}&action=${action}${query}`;
}

/** Refresh the CSRF token for subsequent POSTs. */
export async function refreshCsrf() {
  const { data } = await apiRoute('auth', 'csrf');
  if (data && data.csrf_token) csrfToken = data.csrf_token;
  return csrfToken;
}

/** Quick reachability probe used by the setup screen. */
export async function probeServer(url) {
  const clean = String(url || '').trim().replace(/\/+$/, '');
  const res = await fetch(`${clean}/api.php?action=current_user`, {
    headers: { Accept: 'application/json' },
    credentials: 'include',
  });
  const text = await res.text();
  JSON.parse(text); // throws if this isn't the Respawn Logics API
  return true;
}

// ── Auth ────────────────────────────────────────────────────────────────────
export async function login(email, password) {
  const r = await apiRoute('auth', 'login', { method: 'POST', body: { email, password } });
  if (r.data && r.data.success && r.data.user) {
    await refreshCsrf();
  }
  return r;
}

export async function logout() {
  try {
    await apiRoute('auth', 'logout', { method: 'POST', body: {} });
  } catch (e) {
    // Ignore network errors on logout — we drop local state regardless.
  }
  csrfToken = null;
}

/** Restore an existing session (also returns a fresh CSRF token). */
export async function fetchCurrentUser() {
  const { status, data } = await rawRequest('/api.php?action=current_user');
  if (status === 200 && data && data.success) {
    if (data.csrf_token) csrfToken = data.csrf_token;
    return data.user;
  }
  return null;
}

// ── Dashboard ───────────────────────────────────────────────────────────────
export const getDashboardStats = () => apiRoute('dashboard', 'get_stats');
export const toggleTask = (task_id) =>
  apiRoute('dashboard', 'toggle_task', { method: 'POST', body: { task_id } });
export const addTask = (task_name, task_description = '') =>
  apiRoute('dashboard', 'add_task', { method: 'POST', body: { task_name, task_description } });

// ── Attendance ──────────────────────────────────────────────────────────────
export const getAttendanceStatus = () => apiRoute('attendance', 'status');
export const getTimesheet = () => apiRoute('attendance', 'timesheet');
export const clockIn = () => apiRoute('attendance', 'clock_in', { method: 'POST', body: {} });
export const clockOut = () => apiRoute('attendance', 'clock_out', { method: 'POST', body: {} });
export const getAttendanceApprovals = () => apiRoute('attendance', 'pending_approvals');
export const approveTimesheet = (record_id) =>
  apiRoute('attendance', 'approve_timesheet', { method: 'POST', body: { record_id } });

// ── Leaves ──────────────────────────────────────────────────────────────────
export const getLeaveBalances = () => apiRoute('leaves', 'balances');
export const getMyLeaveRequests = () => apiRoute('leaves', 'my_requests');
export const applyLeave = (body) => apiRoute('leaves', 'apply', { method: 'POST', body });
export const getLeaveApprovals = () => apiRoute('leaves', 'pending_approvals');
export const decideLeave = (request_id, decision, comments = '') =>
  apiRoute('leaves', 'approve_reject', { method: 'POST', body: { request_id, decision, comments } });

// ── Payslips ────────────────────────────────────────────────────────────────
export const getMyPayslips = () => apiRoute('payroll_engine', 'my_payslips');
export const payslipPdfUrl = (id) => fileUrl('payroll_engine', 'download_payslip', `&id=${id}`);

// ── Announcements & notifications ───────────────────────────────────────────
export const getAnnouncements = () => apiRoute('announcements', 'fetch_posts');
export const announcementImageUrl = (id) =>
  fileUrl('announcements', 'download_attachment', `&id=${id}`);
export const getUnreadNotifications = () => apiRoute('notifications', 'fetch_unread');
export const markNotificationRead = (id) =>
  apiRoute('notifications', 'mark_read', { method: 'POST', body: { id } });
export const markAllNotificationsRead = () =>
  apiRoute('notifications', 'mark_all_read', { method: 'POST', body: {} });

// ── HR cases (employee relations) ───────────────────────────────────────────
export const getHrCases = () => rawRequest('/api/index.php?route=employee_relations');
export const addHrCase = (name, stage = 'Reported') =>
  apiRoute('employee_relations', 'add', { method: 'POST', body: { action: 'add', name, stage } });
export const updateHrCaseStage = (id, stage) =>
  apiRoute('employee_relations', 'update_stage', {
    method: 'POST',
    body: { action: 'update_stage', id, stage },
  });
