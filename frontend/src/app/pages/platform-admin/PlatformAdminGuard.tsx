import { Navigate } from "react-router-dom";
import { useAuth } from "../../context/AuthContext";

/**
 * PlatformAdminGuard — wraps all /platform-admin/* routes.
 * Redirects anyone who is NOT a Platform_Admin back to /dashboard.
 */
export function PlatformAdminGuard({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth();
  if (loading) return null;
  const isPlatformAdmin =
    user?.role === "Platform_Admin" || user?.roles?.includes("Platform_Admin");
  if (!user || !isPlatformAdmin) return <Navigate to="/dashboard" replace />;
  return <>{children}</>;
}
