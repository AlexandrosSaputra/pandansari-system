import type { UnitType } from '../../types/api'

export interface NavigationItem {
  group: string
  label: string
  description: string
  to: string
  permission: string
  icon: string
  transaction?: boolean
  workspaceType?: UnitType
  superadminOnly?: boolean
}

export const navigation: NavigationItem[] = [
  {
    group: 'Ruang kerja',
    label: 'Dashboard',
    description: 'Pantau aktivitas dan lanjutkan pekerjaan operasional Anda.',
    to: '/dashboard',
    permission: 'dashboard.view',
    icon: 'dashboard',
  },
  {
    group: 'Transaksi',
    label: 'POS',
    description: 'Kelola transaksi penjualan pada outlet aktif.',
    to: '/pos',
    permission: 'transaction.create',
    icon: 'pos',
    transaction: true,
    workspaceType: 'OUTLET',
  },
  {
    group: 'Transaksi',
    label: 'Ticketing',
    description: 'Kelola transaksi layanan ticketing pada unit aktif.',
    to: '/ticketing',
    permission: 'transaction.create',
    icon: 'ticket',
    transaction: true,
    workspaceType: 'TICKETING',
  },
  {
    group: 'Transaksi',
    label: 'Riwayat Transaksi',
    description: 'Tinjau keranjang dan transaksi pembayaran sesuai lingkup akses Anda.',
    to: '/transactions',
    permission: 'transaction.view',
    icon: 'history',
    transaction: true,
  },
  {
    group: 'Inventori',
    label: 'Stok',
    description: 'Pantau saldo stok produk pada gudang dan unit operasional.',
    to: '/inventory',
    permission: 'inventory.view',
    icon: 'stock',
  },
  {
    group: 'Inventori',
    label: 'Pergerakan Stok',
    description: 'Telusuri setiap perubahan stok sesuai lingkup akses Anda.',
    to: '/inventory/movements',
    permission: 'inventory.view',
    icon: 'movement',
  },
  {
    group: 'Inventori',
    label: 'Transfer Stok',
    description: 'Kelola distribusi produk dari gudang ke unit operasional.',
    to: '/stock-transfers',
    permission: 'transfer.view',
    icon: 'transfer',
  },
  {
    group: 'Analitik',
    label: 'AI Insight',
    description: 'Prediksi kebutuhan produk dan rekomendasi restock.',
    to: '/forecasts',
    permission: 'ai.view',
    icon: 'forecast',
    superadminOnly: true,
  },
  {
    group: 'Master data',
    label: 'Item',
    description: 'Kelola referensi produk, layanan, dan inventaris.',
    to: '/items',
    permission: 'item.view',
    icon: 'item',
  },
  {
    group: 'Master data',
    label: 'Outlet',
    description: 'Kelola outlet, ticketing, dan katalog setiap lokasi operasional.',
    to: '/operational-units',
    permission: 'operational-unit.view',
    icon: 'unit',
  },
  {
    group: 'Master data',
    label: 'Pengguna',
    description: 'Kelola akun, peran, dan penempatan unit operasional.',
    to: '/users',
    permission: 'user.view',
    icon: 'user',
  },
  {
    group: 'Administrasi',
    label: 'Role & Permission',
    description: 'Atur akses aplikasi berdasarkan tanggung jawab pengguna.',
    to: '/rbac',
    permission: 'rbac.view',
    icon: 'roles',
  },
  {
    group: 'Administrasi',
    label: 'Log Aktivitas',
    description: 'Tinjau jejak perubahan dan aktivitas pengguna.',
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
  if (item.superadminOnly && context.roleSlug !== 'superadmin') return false
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
