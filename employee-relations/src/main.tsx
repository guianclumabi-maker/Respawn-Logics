
  import { createRoot } from "react-dom/client";
  import App from "./app/App.tsx";
  import "./styles/index.css";

  // Global fetch interceptor to handle session expiration
  const originalFetch = window.fetch;
  window.fetch = async (...args) => {
    const response = await originalFetch(...args);
    if (response.status === 401) {
      alert("Session expired. Please log in again.");
      window.location.href = "/respawn-logics/login.php";
    }
    return response;
  };

  createRoot(document.getElementById("root")!).render(<App />);
  