import React, { createContext, useContext, useEffect, useState } from "react";

interface AuthUser {
  id: number;
  name: string;
  profile_image?: string;
  job_title?: string;
  roles: string[];
  permissions: string[];
}

interface AuthContextType {
  user: AuthUser | null;
  loading: boolean;
  hasPermission: (perm: string) => boolean;
  hasRole: (role: string) => boolean;
}

const AuthContext = createContext<AuthContextType>({
  user: null,
  loading: true,
  hasPermission: () => false,
  hasRole: () => false,
});

export const useAuth = () => useContext(AuthContext);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
    fetch(`${API_BASE}/api.php?action=current_user`)
      .then((res) => res.json())
      .then((data) => {
        if (data.success && data.user) {
          setUser(data.user);
          if (data.csrf_token) {
            (window as any).__CSRF_TOKEN__ = data.csrf_token;
          }
        }
      })
      .catch((err) => console.error("Failed to load auth state", err))
      .finally(() => setLoading(false));
  }, []);

  const hasPermission = (perm: string) => {
    return user?.permissions.includes(perm) ?? false;
  };

  const hasRole = (role: string) => {
    return user?.roles.includes(role) ?? false;
  };

  return (
    <AuthContext.Provider value={{ user, loading, hasPermission, hasRole }}>
      {children}
    </AuthContext.Provider>
  );
}
