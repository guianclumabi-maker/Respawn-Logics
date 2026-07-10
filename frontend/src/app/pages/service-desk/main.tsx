
  import { createRoot } from "react-dom/client";
  import App from "./app/App.tsx";
  import { ThemeProvider } from "next-themes";
  import "./styles/index.css";

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
  
