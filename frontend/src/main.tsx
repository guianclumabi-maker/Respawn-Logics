import { createRoot } from "react-dom/client";
import { RouterProvider } from "react-router-dom";
import { router } from "./app/Router";
import { AuthProvider } from "./app/context/AuthContext.tsx";
import { ThemeProvider } from "next-themes";
import * as Sentry from "@sentry/react";
import "./styles/index.css";

Sentry.init({
  dsn: "https://examplePublicKey@o0.ingest.sentry.io/0", // Replace with actual DSN
  integrations: [
    Sentry.browserTracingIntegration(),
    Sentry.replayIntegration(),
  ],
  tracesSampleRate: 1.0,
  replaysSessionSampleRate: 0.1,
  replaysOnErrorSampleRate: 1.0,
});

// Global fetch interceptor to handle session expiration
const originalFetch = window.fetch;
const API_BASE = import.meta.env.VITE_API_BASE_URL || "";
window.fetch = async (...args) => {
  const response = await originalFetch(...args);
  if (response.status === 401) {
    alert("Session expired. Please log in again.");
    window.location.href = `${API_BASE}/login.php`;
  }
  return response;
};

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
