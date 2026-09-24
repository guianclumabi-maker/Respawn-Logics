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

export const DEMO_PERSONAS: Record<'employee' | 'manager' | 'admin', AuthUser> = {
  employee: {
    id: 2,
    name: "David Kim",
    email: "david@respawn.logics",
    roles: ["Employee"],
    role: "Employee",
    permissions: ["leave.request", "attendance.view"],
    job_title: "Frontend Engineer",
    tenant_id: 1,
    employment_status: "Active"
  },
  manager: {
    id: 1,
    name: "Sarah Chen",
    email: "sarah@respawn.logics",
    roles: ["Manager"],
    role: "Manager",
    permissions: [
      "leave.request", "leave.view", "attendance.view", 
      "users.view", "shifts.manage", "performance.manage", 
      "ats.view", "analytics.view"
    ],
    job_title: "Engineering Manager",
    tenant_id: 1,
    employment_status: "Active"
  },
  admin: {
    id: 999,
    name: "Peter Parker",
    email: "demo@respawn.logics",
    roles: ["Super_Admin"],
    role: "Super_Admin",
    is_super: true,
    permissions: [
      "manage_tenant", "view_reports", "manage_users", 
      "users.view", "users.manage", "settings.manage", 
      "payroll.manage", "ats.view", "ats.edit", "elr.view", 
      "performance.manage", "attendance.view", "leave.view", "audit.view"
    ],
    job_title: "Chief People Officer & Super Admin",
    tenant_id: 1,
    employment_status: "Active"
  }
};

interface AuthContextType {
  user: AuthUser | null;
  loading: boolean;
  hasPermission: (perm: string) => boolean;
  hasRole: (role: string | string[]) => boolean;
  switchPersona: (persona: 'employee' | 'manager' | 'admin') => void;
  login: (email: string, password: string) => Promise<{ success: boolean; error?: string; redirect?: string }>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType>({
  user: null,
  loading: true,
  hasPermission: () => false,
  hasRole: () => false,
  switchPersona: () => {},
  login: async () => ({ success: false }),
  logout: async () => {},
});

export const useAuth = () => useContext(AuthContext);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);
  const { setTheme } = useTheme();

  const switchPersona = useCallback((persona: 'employee' | 'manager' | 'admin') => {
    const selected = DEMO_PERSONAS[persona] || DEMO_PERSONAS.admin;
    setUser(selected);
    localStorage.setItem('respawn_demo_persona', persona);
  }, []);

  // ── Bootstrap: fetch current session ──
  useEffect(() => {
    const bootstrap = async () => {
      try {
        const href = window.location.href;
        const isDemo = href.includes('demo=true') || href.includes('demo=employee') || href.includes('demo=manager') || href.includes('demo=admin');
        const storedPersona = localStorage.getItem('respawn_demo_persona') as 'employee' | 'manager' | 'admin' | null;

        if (isDemo) {
          if (href.includes('demo=employee') || href.includes('role=employee')) {
            switchPersona('employee');
          } else if (href.includes('demo=manager') || href.includes('role=manager')) {
            switchPersona('manager');
          } else {
            switchPersona('admin');
          }
          setLoading(false);
          return;
        }

        // Check if we just registered — a one-time login_token may be in the URL hash query string
        const hashPart = window.location.hash;
        const queryStart = hashPart.indexOf('?');
        if (queryStart !== -1) {
          const hashQuery = new URLSearchParams(hashPart.slice(queryStart));
          const loginToken = hashQuery.get('login_token');
          if (loginToken) {
            const exchangeRes = await apiFetch(`${API_BASE}/api.php?action=exchange_token&token=${encodeURIComponent(loginToken)}`, {
              credentials: 'include'
            });
            const exchangeData = await exchangeRes.json();
            if (exchangeData.success) {
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
        } else if (storedPersona) {
          switchPersona(storedPersona);
        } else {
          setUser(null);
        }
      } catch {
        const storedPersona = localStorage.getItem('respawn_demo_persona') as 'employee' | 'manager' | 'admin' | null;
        if (storedPersona) {
          switchPersona(storedPersona);
        } else {
          setUser(null);
        }
      } finally {
        setLoading(false);
      }
    };
    bootstrap();
  }, [switchPersona, setTheme]);

  // ── Login ──
  const login = useCallback(
    async (email: string, password: string): Promise<{ success: boolean; error?: string; redirect?: string }> => {
      const lower = email.toLowerCase().trim();

      // Zero-password Instant Demo bypasses
      if (lower.includes("employee") || lower.includes("david")) {
        switchPersona("employee");
        return { success: true };
      }
      if (lower.includes("manager") || lower.includes("sarah")) {
        switchPersona("manager");
        return { success: true };
      }
      if (lower.includes("admin") || lower.includes("peter") || lower.includes("demo") || lower === "") {
        switchPersona("admin");
        return { success: true };
      }

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
        
        // If credentials failed on demo/disconnected server, gracefully log in as Admin persona
        switchPersona("admin");
        return { success: true };
      } catch {
        // Fallback for presentation & demo mode
        switchPersona("admin");
        return { success: true };
      }
    },
    [switchPersona, setTheme]
  );

  // ── Logout ──
  const logout = useCallback(async () => {
    localStorage.removeItem('respawn_demo_persona');
    try {
      await apiFetch(`${API_BASE}/api/index.php?route=auth&action=logout`, {
        method: "POST",
        headers: {
          "X-CSRF-Token": (window as any).__CSRF_TOKEN__ || ""
        },
        credentials: "include",
      });
      
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
    window.location.hash = "#/login";
  }, []);

  const hasRole = (role: string | string[]) => {
    if (Array.isArray(role)) return role.some((r) => user?.roles?.includes(r));
    return user?.roles?.includes(role) ?? false;
  };

  const hasPermission = (perm: string) => {
    if (user?.is_super || hasRole("Super_Admin") || hasRole("Platform_Admin")) return true;
    return user?.permissions?.includes(perm) ?? false;
  };

  return (
    <AuthContext.Provider value={{ user, loading, hasPermission, hasRole, switchPersona, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}
