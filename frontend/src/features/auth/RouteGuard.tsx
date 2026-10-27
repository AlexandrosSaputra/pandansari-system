import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from './useAuth'
import { AppLoadingScreen } from '../../components/common/AppLoadingScreen'
import { ForbiddenPage } from '../../pages/ForbiddenPage'
export function ProtectedRoute() {
  const auth = useAuth()
  const location = useLocation()
  if (auth.isLoading) return <AppLoadingScreen />
  // A session check that cannot reach the API must not leave a protected page
  // visible behind a retry error. Return the user to the login screen instead.
  if (auth.error)
    return <Navigate to="/login" replace state={{ from: location.pathname }} />
  return auth.isAuthenticated ? (
    <Outlet />
  ) : (
    <Navigate to="/login" replace state={{ from: location.pathname }} />
  )
}
export function PermissionGuard({
  permission,
  transaction = false,
  superadminOnly = false,
}: {
  permission: string
  transaction?: boolean
  superadminOnly?: boolean
}) {
  const auth = useAuth()
  return auth.hasPermission(permission) &&
    (!superadminOnly || auth.isSuperadmin) &&
    (!transaction ||
      ['admin', 'superadmin'].includes(auth.user?.role?.slug ?? '')) ? (
    <Outlet />
  ) : (
    <ForbiddenPage />
  )
}
