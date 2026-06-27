import { createRoot } from "react-dom/client";
import { RouterProvider } from "react-router-dom";
import { router } from "./app/Router";
import { AuthProvider } from "./app/context/AuthContext.tsx";
import { ThemeProvider } from "next-themes";
import * as Sentry from "@sentry/react";
import "./styles/index.css";

const sentryDsn = import.meta.env.VITE_SENTRY_DSN;
if (sentryDsn && sentryDsn !== "https://examplePublicKey@o0.ingest.sentry.io/0") {
  Sentry.init({
    dsn: sentryDsn,
    integrations: [
      Sentry.browserTracingIntegration(),
      Sentry.replayIntegration(),
    ],
    tracesSampleRate: 1.0,
    replaysSessionSampleRate: 0.1,
    replaysOnErrorSampleRate: 1.0,
  });
}

// Global fetch interceptor to handle session expiration
const originalFetch = window.fetch;
const API_BASE = import.meta.env.VITE_API_BASE_URL || "";
window.fetch = async (...args) => {
  const response = await originalFetch(...args);
  if (response.status === 401) {
    const url = typeof args[0] === 'string' ? args[0] : (args[0] instanceof Request ? args[0].url : '');
    // Ignore 401s for initial auth/csrf checks to prevent immediate lockout
    if (!url.includes('action=current_user') && !url.includes('action=csrf') && !url.includes('get_csrf.php') && !url.includes('action=login')) {
      window.location.hash = '#/login';
    }
  }
  return response;
};

// ── PHP legacy link interceptor ───────────────────────────────────────
// Show a warning toast instead of navigating to any *.php page.
function createPhpWarningToast() {
  const existing = document.getElementById('__php_warn_toast');
  if (existing) {
    // Reset animation
    existing.style.animation = 'none';
    void (existing as any).offsetHeight;
    existing.style.animation = '';
    return;
  }
  const toast = document.createElement('div');
  toast.id = '__php_warn_toast';
  toast.innerHTML = `
    <div style="display:flex;align-items:flex-start;gap:12px">
      <div style="font-size:20px;flex-shrink:0">⚠️</div>
      <div>
        <div style="font-weight:700;font-size:14px;color:#fff;margin-bottom:4px">Legacy Page</div>
        <div style="font-size:13px;color:#94a3b8;line-height:1.5">This section is still part of the legacy system and hasn't been migrated to the new portal yet.</div>
      </div>
      <button onclick="this.closest('#__php_warn_toast').remove()" style="flex-shrink:0;background:none;border:none;color:#64748b;font-size:18px;cursor:pointer;line-height:1;padding:0 4px">×</button>
    </div>
  `;
  Object.assign(toast.style, {
    position: 'fixed',
    bottom: '24px',
    right: '24px',
    zIndex: '99999',
    background: '#1e2235',
    border: '1px solid rgba(245,166,35,0.35)',
    borderLeft: '4px solid #f5a623',
    borderRadius: '12px',
    padding: '16px 18px',
    maxWidth: '340px',
    boxShadow: '0 8px 40px rgba(0,0,0,0.5)',
    animation: 'slideInToast 0.3s ease',
    fontFamily: "'Inter', sans-serif",
  });
  // Inject keyframes once
  if (!document.getElementById('__toast_kf')) {
    const s = document.createElement('style');
    s.id = '__toast_kf';
    s.textContent = `@keyframes slideInToast { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }`;
    document.head.appendChild(s);
  }
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 5000);
}

document.addEventListener('click', (e) => {
  const target = (e.target as HTMLElement).closest('a');
  if (!target) return;
  const href = target.getAttribute('href') || '';
  if (href.match(/\.php(\?|#|$)/i) && !href.startsWith('javascript')) {
    e.preventDefault();
    e.stopPropagation();
    createPhpWarningToast();
  }
}, true);

// Cross-tab theme sync: listen for theme changes broadcast by the main PHP platform
// or any other React frontend. BroadcastChannel fires in ALL open same-origin tabs.
(function setupThemeSync() {
  const applyTheme = (theme: string) => {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem('theme', theme); } catch(e) {}
  };

  // Primary: BroadcastChannel (instant, all tabs)
  try {
    const bc = new BroadcastChannel('respawn_theme');
    bc.onmessage = (e: MessageEvent) => {
      if (e.data?.theme) applyTheme(e.data.theme);
    };
  } catch(e) {}

  // Fallback: storage event (fires in other tabs when localStorage changes)
  window.addEventListener('storage', (e: StorageEvent) => {
    if (e.key === 'theme' && e.newValue) applyTheme(e.newValue);
  });
})();

async function boot() {
  try {
    const res = await originalFetch(`${API_BASE}/api/index.php?route=auth&action=csrf`, {
      credentials: "include"
    });
    const data = await res.json();
    if (data.success) {
      (window as any).__CSRF_TOKEN__ = data.csrf_token;
    }
  } catch (e) {
    console.error("Failed to fetch CSRF token on boot", e);
  }

  createRoot(document.getElementById("root")!).render(
    <ThemeProvider attribute="data-theme" defaultTheme="dark" storageKey="theme">
      {/* @ts-ignore: React 18 type mismatch from Sentry */}
      <Sentry.ErrorBoundary fallback={<div className="p-8 text-red-500">Something went wrong. Please reload the page.</div>}>
        <AuthProvider>
          <RouterProvider router={router} />
        </AuthProvider>
      </Sentry.ErrorBoundary>
    </ThemeProvider>
  );
}

boot();
