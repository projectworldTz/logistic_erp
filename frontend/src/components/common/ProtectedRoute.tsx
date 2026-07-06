import { Navigate, Outlet } from 'react-router-dom';
import { useAuthStore } from '../../hooks/useAuth';

interface ProtectedRouteProps {
  requireSuperAdmin?: boolean;
  requirePortalUser?: boolean;
}

export function ProtectedRoute({ requireSuperAdmin = false, requirePortalUser = false }: ProtectedRouteProps) {
  const { token, user } = useAuthStore();

  if (!token || !user) {
    return <Navigate to="/login" replace />;
  }

  if (requireSuperAdmin && !user.is_super_admin) {
    return <Navigate to={user.customer_id ? '/portal/dashboard' : '/app/dashboard'} replace />;
  }

  if (requirePortalUser && !user.customer_id) {
    return <Navigate to={user.is_super_admin ? '/platform' : '/app/dashboard'} replace />;
  }

  if (!requireSuperAdmin && !requirePortalUser) {
    if (user.is_super_admin) {
      return <Navigate to="/platform" replace />;
    }
    if (user.customer_id) {
      return <Navigate to="/portal/dashboard" replace />;
    }
  }

  return <Outlet />;
}
