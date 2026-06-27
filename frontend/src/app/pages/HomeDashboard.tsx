import { useEffect } from "react";

const API_BASE =
  import.meta.env.VITE_API_BASE_URL ||
  (window.location.origin +
    (window.location.hostname === "localhost" ? "/respawn-logics" : ""));

export function HomeDashboard() {
  useEffect(() => {
    window.location.replace(`${API_BASE}/pages/dashboard.php`);
  }, []);
  return null;
}
