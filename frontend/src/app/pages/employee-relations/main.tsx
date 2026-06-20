
  import { createRoot } from "react-dom/client";
  import App from "./app/App.tsx";
  import "./styles/index.css";
  import { ThemeProvider } from "next-themes";

  // Global fetch interceptor to handle session expiration
  const originalFetch = window.fetch;
  window.fetch = async (...args) => {
    const response = await originalFetch(...args);
    if (response.status === 401) {
      alert("Session expired. Please log in again.");
      window.location.href = (window.location.hostname === 'localhost' ? '/respawn-logics' : '') + "/login.php";
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

  createRoot(document.getElementById("root")!).render(
    <ThemeProvider attribute="data-theme" defaultTheme="dark" storageKey="theme">
      <App />
    </ThemeProvider>
  );
  