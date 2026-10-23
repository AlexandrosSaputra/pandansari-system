import { SummaryCard } from '../components/SummaryCard'
import { RecentActivity } from '../components/RecentActivity'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Button, Card, PageHeader } from '../../../components/ui'
import {
  isNavigationItemVisible,
  navigation,
} from '../../../components/common/navigation'
import { NavigationIcon } from '../../../components/common/NavigationIcon'
import { useList } from '../../../lib/queries'
import type {
  InventoryBalance,
  StockTransfer,
  Transaction,
} from '../../../types/api'
import { useAuth } from '../../auth/useAuth'
import { StockForm } from '../../inventory/StockForm'
import { HealthStatusCard } from '../components/HealthStatusCard'
export function DashboardPage() {
  const auth = useAuth()
  const unit = auth.user?.operational_unit
  const canTransact = ['admin', 'superadmin'].includes(
    auth.user?.role?.slug ?? '',
  )
  const inventory = useList<InventoryBalance>(
    auth.canWarehouse
      ? '/inventory/warehouse'
      : `/operational-units/${unit?.id}/inventory`,
    { per_page: 1 },
    auth.hasPermission('inventory.view') && (auth.canWarehouse || !!unit),
  )
  const transfers = useList<StockTransfer>(
    '/stock-transfers',
    { status: 'PENDING', per_page: 5 },
    auth.hasPermission('transfer.view'),
  )
  const transactions = useList<Transaction>(
    '/transactions',
    { status: 'PAID', per_page: 5 },
    canTransact && auth.hasPermission('transaction.view'),
  )
  const [stock, setStock] = useState<'in' | 'out' | null>(null)
  const title = auth.isSuperadmin
    ? 'Ringkasan Operasional'
    : auth.canWarehouse
      ? 'Gudang Utama'
      : (unit?.name ?? 'Dashboard')
  const shortcuts = navigation.filter(
    (item) =>
      item.to !== '/dashboard' &&
      isNavigationItemVisible(item, {
        hasPermission: auth.hasPermission,
        roleSlug: auth.user?.role?.slug,
        unitType: unit?.type,
      }),
  )
  const transactionWorkspaces = shortcuts.filter((item) => item.workspaceType)
  return (
    <>
      <PageHeader
        title={title}
        description={`Selamat datang, ${auth.user?.name}. Pantau aktivitas dan lanjutkan pekerjaan Anda.`}
        actions={
          <>
            {transactionWorkspaces.map((item) => (
              <Link className="btn btn-primary" to={item.to} key={item.to}>
                Buka {item.label} ↗
              </Link>
            ))}
            {auth.canWarehouse && auth.hasPermission('inventory.stock-in') && (
              <Button onClick={() => setStock('in')}>+ Stok Masuk</Button>
            )}
            {auth.canWarehouse && auth.hasPermission('inventory.stock-out') && (
              <Button variant="secondary" onClick={() => setStock('out')}>
                Stok Keluar
              </Button>
            )}
          </>
        }
      />
      <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {auth.hasPermission('inventory.view') && (
          <SummaryCard title="Jenis produk dalam inventori" query={inventory}>
            {auth.canWarehouse ? 'Gudang Utama' : unit?.name}
          </SummaryCard>
        )}
        {auth.hasPermission('transfer.view') && (
          <SummaryCard title="Transfer menunggu respons" query={transfers}>
            <Link className="link" to="/stock-transfers?status=PENDING">
              Lihat transfer
            </Link>
          </SummaryCard>
        )}
        {canTransact && auth.hasPermission('transaction.view') && (
          <SummaryCard title="Transaksi dibayar" query={transactions}>
            Seluruh periode ? sesuai lingkup akses
          </SummaryCard>
        )}
      </div>
      <Card className="mb-6 p-5">
        <h2 className="mb-4">Akses Cepat</h2>
        <div className="flex flex-wrap gap-2">
          {shortcuts.map((item) => (
            <Link className="btn btn-secondary" to={item.to} key={item.to}>
              <NavigationIcon name={item.icon} className="size-3.5" />
              {item.label}
            </Link>
          ))}
        </div>
      </Card>
      <RecentActivity
        transfers={transfers}
        transactions={transactions}
        showTransfers={auth.hasPermission('transfer.view')}
        showTransactions={canTransact && auth.hasPermission('transaction.view')}
      />
      {import.meta.env.DEV && (
        <details className="mt-8 max-w-xl text-xs text-slate-400">
          <summary className="mb-3 cursor-pointer">
            Status koneksi pengembangan
          </summary>
          <HealthStatusCard />
        </details>
      )}
      {stock && (
        <StockForm
          direction={stock}
          onClose={() => setStock(null)}
          onSaved={() => setStock(null)}
        />
      )}
    </>
  )
}
