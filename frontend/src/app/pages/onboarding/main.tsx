
  import { createRoot } from "react-dom/client";
  import App from "./app/App.tsx";
  import "./styles/index.css";

  import { ThemeProvider } from "next-themes";

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
    <ThemeProvider attribute="data-theme" defaultTheme="dark" storageKey="theme">
      <App />
    </ThemeProvider>
  );
