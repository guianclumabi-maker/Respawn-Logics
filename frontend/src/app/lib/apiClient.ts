// Centralized fetch wrapper. Always sends credentials + CSRF token on mutations.
const API_BASE =
  import.meta.env.VITE_API_BASE_URL ||
  window.location.origin +
    (window.location.hostname === "localhost" ? "/respawn-logics" : "");

/** Fetch (and cache) a fresh CSRF token from the server. */
export async function getCsrfToken(): Promise<string> {
  try {
    const r = await fetch(`${API_BASE}/api/index.php?route=auth&action=csrf`, { credentials: "include" });
    const d = await r.json();
    if (d?.success && d.csrf_token) {
      (window as any).__CSRF_TOKEN__ = d.csrf_token;
      return d.csrf_token;
    }
  } catch { /* fall through */ }
  return (window as any).__CSRF_TOKEN__ ?? "";
}

export async function apiFetch(path: string, options: RequestInit = {}): Promise<Response> {
  const method = (options.method ?? "GET").toUpperCase();
  const isMutation = !["GET", "HEAD", "OPTIONS"].includes(method);

  const headers = new Headers(options.headers ?? {});
  // Don't set Content-Type for FormData — browser sets it with boundary automatically
  if (!headers.has("Content-Type") && options.body && !(options.body instanceof FormData)) {
    headers.set("Content-Type", "application/json");
  }

  if (isMutation) {
    // Always fetch a fresh token for every mutation to prevent stale-token 403s
    const token = await getCsrfToken();
    if (token) headers.set("X-CSRF-Token", token);
  }

  return fetch(`${API_BASE}${path}`, { ...options, headers, credentials: "include" });
}

