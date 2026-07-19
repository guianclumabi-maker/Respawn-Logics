import React, { createContext, useContext, useEffect, useState, useCallback } from "react";
import { useTheme } from "next-themes";
import { apiFetch } from "../lib/apiClient";

const API_BASE =
  import.meta.env.VITE_API_BASE_URL ||
  window.location.origin +
    (window.location.hostname === "localhost" ? "/respawn-logics" : "");

interface AuthUser {
  id: number;
  name: string;
  email?: string;
  profile_image?: string;
  job_title?: string;
  roles: string[];
  role?: string;
  permissions: string[];
  is_super?: boolean;
  tenant_id?: number;
  tenant_setup_mode?: string;
  theme?: string;
  employment_status?: string;
  employee_id?: number;
  tier_config?: {
    default_scope: string;
    org_units: boolean;
    custom_roles?: boolean;
    roles_limit?: number;
  };
}

interface AuthContextType {
  user: AuthUser | null;
  loading: boolean;
  hasPermission: (perm: string) => boolean;
  hasRole: (role: string | string[]) => boolean;
  login: (email: string, password: string) => Promise<{ success: boolean; error?: string; redirect?: string }>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType>({
  user: null,
  loading: true,
  hasPermission: () => false,
  hasRole: () => false,
  login: async () => ({ success: false }),
  logout: async () => {},
});

export const useAuth = () => useContext(AuthContext);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);
  const { setTheme } = useTheme();

  // ── Bootstrap: fetch current session ──
  useEffect(() => {
    const bootstrap = async () => {
      try {
        const isDemo = window.location.href.includes('demo=true');
        if (isDemo) {
            setUser({
                id: 999,
                name: "Peter Parker",
                email: "demo@respawn.logics",
                roles: ["Super_Admin"],
                permissions: ["manage_tenant", "view_reports", "manage_users"],
                job_title: "Your friendly Neighborhood Spiderman",
                tenant_id: 1,
            });
            setLoading(false);
            return;
        }

        // Check if we just registered — a one-time login_token may be in the URL hash query string
        // e.g. #/onboarding?login_token=abc123 or #/dashboard?login_token=abc123
        const hashPart = window.location.hash; // e.g. "#/onboarding?login_token=abc123"
        const queryStart = hashPart.indexOf('?');
        if (queryStart !== -1) {
          const hashQuery = new URLSearchParams(hashPart.slice(queryStart));
          const loginToken = hashQuery.get('login_token');
          if (loginToken) {
            // Exchange the one-time token for a proper session
            const exchangeRes = await apiFetch(`${API_BASE}/api.php?action=exchange_token&token=${encodeURIComponent(loginToken)}`, {
              credentials: 'include'
            });
            const exchangeData = await exchangeRes.json();
            console.log("Token exchange response:", exchangeData);
            if (!exchangeData.success) {
              console.error("Token exchange failed:", exchangeData.error);
            } else {
              // Clean the token out of the URL so it can't be reused via browser history
              const cleanHash = hashPart.slice(0, queryStart);
              window.history.replaceState(null, '', window.location.pathname + window.location.search + cleanHash);
            }
          }
        }

        // Now do the normal session check
        const res = await apiFetch(`${API_BASE}/api.php?action=current_user`, { credentials: 'include' });
        const data = await res.json();
        if (data.success && data.user) {
          if (data.user.must_change_password) {
            window.location.href = `${API_BASE}/login.php?step=set_password`;
            return;
          }
          setUser(data.user);
          if (data.user.theme) setTheme(data.user.theme);
          if (data.csrf_token) (window as any).__CSRF_TOKEN__ = data.csrf_token;
        } else {
          setUser(null);
        }
      } catch {
        setUser(null);
      } finally {
        setLoading(false);
      }
    };
    bootstrap();
  }, []);

  // ── Login ──
  const login = useCallback(
    async (email: string, password: string): Promise<{ success: boolean; error?: string; redirect?: string }> => {
      let token = (window as any).__CSRF_TOKEN__;
      if (!token) {
        try {
          const tokenRes = await apiFetch(`${API_BASE}/api/index.php?route=auth&action=csrf`, { credentials: "include" });
          const tokenData = await tokenRes.json();
          if (tokenData.success && tokenData.csrf_token) {
            token = (window as any).__CSRF_TOKEN__ = tokenData.csrf_token;
          }
        } catch (e) {
          console.error("Failed to auto-fetch CSRF token on login", e);
        }
      }

      try {
        const res = await apiFetch(
          `${API_BASE}/api/index.php?route=auth&action=login`,
          {
            method: "POST",
            headers: { 
              "Content-Type": "application/json",
              "X-CSRF-Token": token || ""
            },
            credentials: "include",
            body: JSON.stringify({ email, password }),
          }
        );
        const data = await res.json();
        
        if (data.success) {
          if (data.redirect) {
            return { success: true, redirect: data.redirect };
          }
          if (data.user) {
            setUser(data.user);
            if (data.user.theme) setTheme(data.user.theme);
            return { success: true };
          }
        }
        
        return { success: false, error: data.error || "Invalid email or password." };
      } catch {
        return { success: false, error: "Unable to reach the server. Please try again." };
      }
    },
    []
  );

  // ── Logout ──
  const logout = useCallback(async () => {
    try {
      await apiFetch(`${API_BASE}/api/index.php?route=auth&action=logout`, {
        method: "POST",
        headers: {
          "X-CSRF-Token": (window as any).__CSRF_TOKEN__ || ""
        },
        credentials: "include",
      });
      
      // Fetch a fresh CSRF token for the new guest session
      const tokenRes = await apiFetch(`${API_BASE}/api/index.php?route=auth&action=csrf`, { credentials: "include" });
      const tokenData = await tokenRes.json();
      if (tokenData.success && tokenData.csrf_token) {
        (window as any).__CSRF_TOKEN__ = tokenData.csrf_token;
      } else {
        (window as any).__CSRF_TOKEN__ = undefined;
      }
    } catch {
      (window as any).__CSRF_TOKEN__ = undefined;
    }
    setUser(null);
    // Navigate to login via hash
    window.location.hash = "#/login";
  }, []);

  const hasRole = (role: string | string[]) => {
    if (Array.isArray(role)) return role.some((r) => user?.roles?.includes(r));
    return user?.roles?.includes(role) ?? false;
  };

  const hasPermission = (perm: string) => {
    // Platform admins bypass permission checks. Rely on the explicit is_super flag from the
    // backend (deterministic) as well as the role name, so the sidebar never depends on the
    // permissions cache being warm.
    if (user?.is_super || hasRole("Super_Admin") || hasRole("Platform_Admin")) return true;
    return user?.permissions?.includes(perm) ?? false;
  };

  return (
    <AuthContext.Provider value={{ user, loading, hasPermission, hasRole, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}
