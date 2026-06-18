
  import { createRoot } from "react-dom/client";
  import App from "./app/App.tsx";
  import { AuthProvider } from "./app/context/AuthContext.tsx";
  import { ThemeProvider } from "next-themes";
  import "./styles/index.css";

  // Global fetch interceptor to handle session expiration
  const originalFetch = window.fetch;
  const API_BASE = import.meta.env.VITE_API_BASE_URL;
  window.fetch = async (...args) => {
    const response = await originalFetch(...args);
    if (response.status === 401) {
      alert("Session expired. Please log in again.");
      if (!API_BASE) {
        window.location.href = "/respawn-logics/login.php"; // Fallback if env is missing
      } else {
        window.location.href = `${API_BASE}/login.php`;
      }
    }
    return response;
  };

  createRoot(document.getElementById("root")!).render(
    <ThemeProvider attribute="data-theme" defaultTheme="dark">
      <AuthProvider>
        <App />
      </AuthProvider>
    </ThemeProvider>
  );
  
