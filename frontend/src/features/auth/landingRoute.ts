import type { AuthUser } from '../../types/api'

type WorkspaceIdentity = Pick<AuthUser, 'role' | 'operational_unit'>

export function landingRouteFor(user?: WorkspaceIdentity | null): string {
  if (user?.role?.slug === 'admin') {
    if (user.operational_unit?.type === 'TICKETING') return '/ticketing'
    if (user.operational_unit?.type === 'OUTLET') return '/pos'
  }

  if (user?.role?.slug === 'warehouse') return '/inventory'

  return '/dashboard'
}
