import React, { createContext, useContext, useEffect, useState, useCallback } from "react";
import { useTheme } from "next-themes";

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
  permissions: string[];
  tenant_id?: number;
  tenant_setup_mode?: string;
  theme?: string;
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
    fetch(`${API_BASE}/api.php?action=current_user`, { credentials: "include" })
      .then((res) => res.json())
      .then((data) => {
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
      })
      .catch(() => setUser(null))
      .finally(() => setLoading(false));
  }, []);

  // ── Login ──
  const login = useCallback(
    async (email: string, password: string): Promise<{ success: boolean; error?: string; redirect?: string }> => {
      if (!(window as any).__CSRF_TOKEN__) {
        try {
          const tokenRes = await fetch(`${API_BASE}/api/index.php?route=auth&action=csrf`, { credentials: "include" });
          const tokenData = await tokenRes.json();
          if (tokenData.success && tokenData.csrf_token) {
            (window as any).__CSRF_TOKEN__ = tokenData.csrf_token;
          }
        } catch (e) {
          console.error("Failed to fetch initial CSRF token", e);
        }
      }

      try {
        const res = await fetch(
          `${API_BASE}/api/index.php?route=auth&action=login`,
          {
            method: "POST",
            headers: { 
              "Content-Type": "application/json",
              "X-CSRF-Token": (window as any).__CSRF_TOKEN__ || ""
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
      await fetch(`${API_BASE}/api/index.php?route=auth&action=logout`, {
        method: "POST",
        headers: {
          "X-CSRF-Token": (window as any).__CSRF_TOKEN__ || ""
        },
        credentials: "include",
      });
    } catch {
      // ignore
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
    if (hasRole("Super_Admin")) return true;
    return user?.permissions?.includes(perm) ?? false;
  };

  return (
    <AuthContext.Provider value={{ user, loading, hasPermission, hasRole, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}
