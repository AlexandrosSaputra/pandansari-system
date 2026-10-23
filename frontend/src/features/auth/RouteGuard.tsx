import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from './useAuth'
import { Skeleton } from '../../components/ui'
import { ForbiddenPage } from '../../pages/ForbiddenPage'
export function ProtectedRoute() {
  const auth = useAuth()
  const location = useLocation()
  if (auth.isLoading)
    return (
      <div className="mx-auto max-w-4xl p-8">
        <Skeleton rows={7} />
      </div>
    )
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
}: {
  permission: string
  transaction?: boolean
}) {
  const auth = useAuth()
  return auth.hasPermission(permission) &&
    (!transaction ||
      ['admin', 'superadmin'].includes(auth.user?.role?.slug ?? '')) ? (
    <Outlet />
  ) : (
    <ForbiddenPage />
  )
}
