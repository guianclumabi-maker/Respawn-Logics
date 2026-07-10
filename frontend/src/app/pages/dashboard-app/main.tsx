
import { createRoot } from "react-dom/client";
import * as Sentry from "@sentry/react";
import App from "./app/App.tsx";
import "./styles/index.css";

import { ThemeProvider } from "next-themes";

if (import.meta.env.VITE_SENTRY_DSN) {
  Sentry.init({
    dsn: import.meta.env.VITE_SENTRY_DSN,
    integrations: [
      Sentry.browserTracingIntegration(),
      Sentry.replayIntegration(),
    ],
    tracesSampleRate: 1.0,
    replaysSessionSampleRate: 0.1,
    replaysOnErrorSampleRate: 1.0,
  });
}

(function setupThemeSync() {
  const applyTheme = (theme: string) => {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem('theme', theme); } catch(e) {}
  };
  try {
    const bc = new BroadcastChannel('respawn_theme');
    bc.onmessage = (e: MessageEvent) => { if (e.data?.theme) applyTheme(e.data.theme); };
  } catch(e) {}
  window.addEventListener('storage', (e: StorageEvent) => {
    if (e.key === 'theme' && e.newValue) applyTheme(e.newValue);
  });
})();

createRoot(document.getElementById("root")!).render(
  <ThemeProvider attribute="data-theme" defaultTheme="system" storageKey="theme">
    <App />
  </ThemeProvider>
);
