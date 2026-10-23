import type { UnitType } from '../../types/api'

export interface NavigationItem {
  group: string
  label: string
  to: string
  permission: string
  icon: string
  transaction?: boolean
  workspaceType?: UnitType
}

export const navigation: NavigationItem[] = [
  {
    group: 'Ruang kerja',
    label: 'Dashboard',
    to: '/dashboard',
    permission: 'dashboard.view',
    icon: 'dashboard',
  },
  {
    group: 'Transaksi',
    label: 'POS',
    to: '/pos',
    permission: 'transaction.create',
    icon: 'pos',
    transaction: true,
    workspaceType: 'OUTLET',
  },
  {
    group: 'Transaksi',
    label: 'Ticketing',
    to: '/ticketing',
    permission: 'transaction.create',
    icon: 'ticket',
    transaction: true,
    workspaceType: 'TICKETING',
  },
  {
    group: 'Transaksi',
    label: 'Riwayat Transaksi',
    to: '/transactions',
    permission: 'transaction.view',
    icon: 'history',
    transaction: true,
  },
  {
    group: 'Inventori',
    label: 'Stok',
    to: '/inventory',
    permission: 'inventory.view',
    icon: 'stock',
  },
  {
    group: 'Inventori',
    label: 'Pergerakan Stok',
    to: '/inventory/movements',
    permission: 'inventory.view',
    icon: 'movement',
  },
  {
    group: 'Inventori',
    label: 'Transfer Stok',
    to: '/stock-transfers',
    permission: 'transfer.view',
    icon: 'transfer',
  },
  {
    group: 'Master data',
    label: 'Unit Operasional',
    to: '/operational-units',
    permission: 'operational-unit.view',
    icon: 'unit',
  },
  {
    group: 'Master data',
    label: 'Item',
    to: '/items',
    permission: 'item.view',
    icon: 'item',
  },
  {
    group: 'Master data',
    label: 'Pengguna',
    to: '/users',
    permission: 'user.view',
    icon: 'user',
  },
  {
    group: 'Administrasi',
    label: 'Role & Permission',
    to: '/rbac',
    permission: 'rbac.view',
    icon: 'roles',
  },
  {
    group: 'Administrasi',
    label: 'Log Aktivitas',
    to: '/activity-logs',
    permission: 'audit.view',
    icon: 'log',
  },
]

export function isNavigationItemVisible(
  item: NavigationItem,
  context: {
    hasPermission: (permission: string) => boolean
    roleSlug?: string
    unitType?: UnitType
  },
) {
  if (!context.hasPermission(item.permission)) return false
  if (
    item.transaction &&
    !['admin', 'superadmin'].includes(context.roleSlug ?? '')
  )
    return false

  return (
    !item.workspaceType ||
    context.roleSlug === 'superadmin' ||
    item.workspaceType === context.unitType
  )
}
