// Centralized fetch wrapper. Always sends credentials + CSRF token on mutations.
const API_BASE =
  import.meta.env.VITE_API_BASE_URL ||
  window.location.origin +
    (window.location.hostname === "localhost" ? "/respawn-logics" : "");

export async function apiFetch(path: string, options: RequestInit = {}): Promise<Response> {
  const method = (options.method ?? "GET").toUpperCase();
  const isMutation = !["GET", "HEAD", "OPTIONS"].includes(method);

  const headers = new Headers(options.headers ?? {});
  if (!headers.has("Content-Type") && options.body && !(options.body instanceof FormData)) {
    headers.set("Content-Type", "application/json");
  }
  
  if (isMutation) {
    let token = (window as any).__CSRF_TOKEN__;
    if (!token) {
      // lazy-fetch once if missing (e.g. deep link before login flow primed it)
      try {
        const r = await fetch(`${API_BASE}/api/index.php?route=auth&action=csrf`, { credentials: "include" });
        const d = await r.json();
        if (d?.success && d.csrf_token) token = (window as any).__CSRF_TOKEN__ = d.csrf_token;
      } catch { /* fail closed — server will 403 */ }
    }
    if (token) headers.set("X-CSRF-Token", token);
  }

  return fetch(`${API_BASE}${path}`, { ...options, headers, credentials: "include" });
}
