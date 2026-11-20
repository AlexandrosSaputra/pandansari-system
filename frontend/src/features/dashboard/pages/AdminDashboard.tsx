import { useState, type ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { NavigationIcon } from '../../../components/common/NavigationIcon'
import {
  isNavigationItemVisible,
  navigation,
} from '../../../components/common/navigation'
import { useTopbarOverride } from '../../../components/common/topbarOverride'
import { Badge, Button, ErrorState, Skeleton } from '../../../components/ui'
import { StockUrgencyIndicator } from '../../../components/ui/StockUrgencyIndicator'
import { useList } from '../../../lib/queries'
import type { StockTransfer } from '../../../types/api'
import { formatCurrency, formatDate, formatNumber } from '../../../utils/format'
import { useAuth } from '../../auth/useAuth'
import { TransactionDetail } from '../../transactions/TransactionDetail'
import { TransferDetail } from '../../transfers/TransferDetail'
import {
  useAdminInventory,
  useAdminSales,
  type AdminSales,
} from '../hooks/useAdminDashboard'
import './admin-dashboard.css'

const stockGroups = [
  { key: 'HEALTHY', label: 'Aman / tersedia', color: '#03695e' },
  { key: 'NEAR_MINIMUM', label: 'Mendekati minimum', color: '#d49a2d' },
  { key: 'BELOW_MINIMUM', label: 'Stok rendah', color: '#dd743b' },
  { key: 'OUT_OF_STOCK', label: 'Stok habis', color: '#bd4a54' },
] as const

const dateTimeWib = (value: string | null) =>
  value
    ? new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Asia/Jakarta',
      }).format(new Date(value))
    : '—'

function Empty({
  icon,
  title,
  children,
}: {
  icon: string
  title: string
  children: ReactNode
}) {
  return (
    <div className="ad-empty">
      <span className="ad-empty-icon">
        <NavigationIcon name={icon} />
      </span>
      <strong>{title}</strong>
      <p>{children}</p>
    </div>
  )
}

function Panel({
  title,
  description,
  action,
  children,
  className = '',
}: {
  title: string
  description: string
  action?: ReactNode
  children: ReactNode
  className?: string
}) {
  return (
    <section className={`ad-panel ${className}`}>
      <header className="ad-panel-heading">
        <div>
          <h2>{title}</h2>
          <p>{description}</p>
        </div>
        {action}
      </header>
      {children}
    </section>
  )
}

function SalesChart({ sales }: { sales: AdminSales }) {
  const ceiling = Math.max(...sales.daily.map((day) => Number(day.revenue)), 1)
  const compact = (value: number) =>
    new Intl.NumberFormat('id-ID', {
      notation: 'compact',
      maximumFractionDigits: 1,
    }).format(value)
  return (
    <figure className="ad-chart" aria-label="Grafik omzet harian dalam rupiah">
      <div className="ad-chart-summary">
        <strong>{formatCurrency(sales.revenue)}</strong>
        <span>
          <i /> Omzet transaksi lunas
        </span>
      </div>
      <div className="ad-plot">
        <div className="ad-axis" aria-hidden="true">
          {[1, 0.75, 0.5, 0.25, 0].map((part) => (
            <span key={part}>
              {ceiling === 1 && part > 0 ? '—' : compact(ceiling * part)}
            </span>
          ))}
        </div>
        <div
          className="ad-bars"
          style={{
            gridTemplateColumns: `repeat(${sales.daily.length}, minmax(0, 1fr))`,
          }}
        >
          {sales.daily.map((day, index) => (
            <div className="ad-bar-column" key={day.date}>
              <div
                className="ad-bar-track"
                tabIndex={0}
                role="img"
                aria-label={`${formatDate(day.date)}: ${formatCurrency(day.revenue)}, ${day.transactions} transaksi`}
              >
                <div
                  className={`ad-bar ${index === sales.daily.length - 1 ? 'ad-bar-today' : ''}`}
                  style={{
                    height: `${(Number(day.revenue) / ceiling) * 100}%`,
                  }}
                />
                <span className="ad-chart-tooltip">
                  {formatDate(day.date)}
                  <strong>{formatCurrency(day.revenue)}</strong>
                  {day.transactions} transaksi
                </span>
              </div>
              <span className="ad-bar-label">
                {sales.daily.length === 7 ||
                index % 5 === 0 ||
                index === sales.daily.length - 1
                  ? `${Number(day.date.slice(-2))}/${Number(day.date.slice(5, 7))}`
                  : ''}
              </span>
            </div>
          ))}
          {sales.transactions === 0 && (
            <div className="ad-chart-empty">
              <strong>Penjualan pertama dimulai di sini</strong>
              <span>Grafik terisi setelah pembayaran berhasil.</span>
            </div>
          )}
        </div>
      </div>
      <figcaption>
        {formatDate(sales.period.from)} – {formatDate(sales.period.to)} · Waktu
        pembayaran WIB
      </figcaption>
    </figure>
  )
}

export function AdminDashboard() {
  const auth = useAuth()
  const unit = auth.user?.operational_unit
  const ticketing = unit?.type === 'TICKETING'
  const [days, setDays] = useState(7)
  const [transactionId, setTransactionId] = useState<number>()
  const [transferId, setTransferId] = useState<number>()
  const canSales = auth.hasPermission('transaction.view') && !!unit
  const canStock = auth.hasPermission('inventory.view') && !!unit
  const canTransfers = auth.hasPermission('transfer.view') && !!unit
  const sales = useAdminSales(unit?.id, days, canSales)
  const inventory = useAdminInventory(unit?.id, canStock)
  const transfers = useList<StockTransfer>(
    '/stock-transfers',
    {
      status: 'PENDING',
      per_page: 4,
      destination_operational_unit_id: unit?.id,
    },
    canTransfers,
  )
  const shortcuts = navigation.filter(
    (item) =>
      item.to !== '/dashboard' &&
      isNavigationItemVisible(item, {
        hasPermission: auth.hasPermission,
        roleSlug: auth.user?.role?.slug,
        unitType: unit?.type,
      }),
  )
  const workspace = shortcuts.find((item) => item.workspaceType)
  const rows = inventory.data ?? []
  const critical = rows
    .filter((row) =>
      ['OUT_OF_STOCK', 'BELOW_MINIMUM'].includes(row.stock_urgency ?? ''),
    )
    .sort(
      (a, b) =>
        (a.stock_urgency === 'OUT_OF_STOCK' ? 0 : 1) -
          (b.stock_urgency === 'OUT_OF_STOCK' ? 0 : 1) ||
        a.available_quantity - b.available_quantity,
    )
  const groups = stockGroups.map((group) => ({
    ...group,
    count: rows.filter((row) => row.stock_urgency === group.key).length,
  }))
  const unknown =
    rows.length - groups.reduce((total, group) => total + group.count, 0)
  let offset = 0
  const segments = groups.map((group) => {
    const start = offset
    offset += rows.length ? (group.count / rows.length) * 100 : 0
    return `${group.color} ${start}% ${offset}%`
  })
  const ring = rows.length
    ? `conic-gradient(${segments.join(',')}, #e5eae7 ${offset}% 100%)`
    : '#e5eae7'
  const refreshing =
    (canSales && sales.isFetching) ||
    (canStock && inventory.isFetching) ||
    (canTransfers && transfers.isFetching)
  const refresh = () => {
    if (canSales) void sales.refetch()
    if (canStock) void inventory.refetch()
    if (canTransfers) void transfers.refetch()
  }
  useTopbarOverride({
    label: 'Dashboard Admin',
    description: `${unit?.name ?? 'Unit operasional'} · Ringkasan aktivitas dan penjualan`,
  })
  const stats = sales.data
  return (
    <div className="admin-dashboard">
      <div className="ad-page-heading">
        <div>
          <span className="ad-eyebrow">RUANG KERJA ADMIN</span>
          <h1>Dashboard {unit?.name ?? 'Admin'}</h1>
          <p>
            Selamat datang, {auth.user?.name ?? 'Admin'}. Siap untuk hari yang
            produktif?
          </p>
        </div>
        <div className="ad-heading-actions">
          <Button
            variant="secondary"
            onClick={refresh}
            disabled={refreshing || !(canSales || canStock || canTransfers)}
          >
            <svg
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.8"
              aria-hidden="true"
            >
              <path d="M20 7v5h-5M4 17v-5h5M5 7a8 8 0 0 1 13-2l2 3M4 16l2 3a8 8 0 0 0 13-2" />
            </svg>
            {refreshing ? 'Memuat…' : 'Perbarui'}
          </Button>
          {workspace && (
            <Link className="btn btn-primary" to={workspace.to}>
              <NavigationIcon name={workspace.icon} />
              Buka {workspace.label}
            </Link>
          )}
        </div>
      </div>
      {!unit && (
        <div className="notice notice-error" role="alert">
          Unit operasional belum tersedia. Hubungi Superadmin untuk memeriksa
          penempatan akun.
        </div>
      )}
      <section className="ad-welcome">
        <div className="ad-welcome-copy">
          <span className="ad-unit-pill">
            <NavigationIcon name="unit" />
            {ticketing ? 'Operasional ticketing' : 'Operasional outlet'}
          </span>
          <h2>Semua aktivitas, satu pandangan.</h2>
          <p>
            Pantau penjualan, cek persediaan, dan lanjutkan pekerjaan Anda dari
            sini.
          </p>
        </div>
        <div className="ad-welcome-art" aria-hidden="true">
          <div className="ad-art-card">
            <span />
            <span />
            <div>
              <i />
              <i />
              <i />
              <i />
              <i />
            </div>
          </div>
          <div className="ad-art-check">✓</div>
        </div>
        <div className="ad-welcome-note">
          <NavigationIcon name="unit" />
          <div>
            <strong>{unit?.name ?? 'Belum ada unit'}</strong>
            <span>Data sesuai unit penugasan Anda</span>
          </div>
        </div>
      </section>
      {canSales && (
        <>
          <div className="ad-section-toolbar">
            <div>
              <h2>Ringkasan penjualan</h2>
              <p>Transaksi lunas · termasuk hari ini · WIB</p>
            </div>
            <div
              className="ad-period"
              role="group"
              aria-label="Periode penjualan"
            >
              {[7, 30].map((value) => (
                <button
                  key={value}
                  type="button"
                  aria-pressed={days === value}
                  onClick={() => setDays(value)}
                >
                  {value} hari terakhir
                </button>
              ))}
            </div>
          </div>
          {sales.error ? (
            <ErrorState
              error={sales.error}
              retry={() => void sales.refetch()}
            />
          ) : sales.isPending ? (
            <Skeleton rows={3} />
          ) : (
            stats && (
              <>
                <div className="ad-stats">
                  {[
                    {
                      label: 'Total omzet',
                      value: formatCurrency(stats.revenue),
                      note: 'Dari pembayaran berhasil',
                      icon: 'forecast',
                      tone: 'jade',
                    },
                    {
                      label: 'Transaksi lunas',
                      value: formatNumber(stats.transactions),
                      note: `Dalam ${days} hari terakhir`,
                      icon: 'history',
                      tone: 'aqua',
                    },
                    {
                      label: ticketing ? 'Jumlah pengunjung' : 'Item terjual',
                      value: formatNumber(
                        ticketing ? stats.visitors : stats.items_sold,
                      ),
                      note: ticketing
                        ? 'Dari tiket yang dibayar'
                        : 'Total kuantitas terjual',
                      icon: ticketing ? 'user' : 'item',
                      tone: 'violet',
                    },
                    {
                      label: 'Rata-rata transaksi',
                      value: formatCurrency(stats.average_transaction),
                      note: 'Omzet per transaksi lunas',
                      icon: 'pos',
                      tone: 'amber',
                    },
                  ].map((stat) => (
                    <section
                      className={`ad-stat ad-stat-${stat.tone}`}
                      key={stat.label}
                    >
                      <div>
                        <span>{stat.label}</span>
                        <span className="ad-stat-icon">
                          <NavigationIcon name={stat.icon} />
                        </span>
                      </div>
                      <strong>{stat.value}</strong>
                      <p>{stat.note}</p>
                    </section>
                  ))}
                </div>
                <div className="ad-grid ad-sales-grid">
                  <Panel
                    title="Tren penjualan"
                    description="Perkembangan omzet setiap hari"
                    action={<span className="ad-soft-badge">{days} hari</span>}
                  >
                    <SalesChart sales={stats} />
                  </Panel>
                  <Panel
                    title={ticketing ? 'Layanan terlaris' : 'Produk terlaris'}
                    description="5 teratas berdasarkan jumlah terjual"
                  >
                    {stats.top_items.length === 0 ? (
                      <Empty icon="item" title="Belum ada item terjual">
                        Item terlaris akan muncul setelah transaksi dibayar.
                      </Empty>
                    ) : (
                      <ol className="ad-top-items">
                        {stats.top_items.map((item, index) => (
                          <li key={item.id}>
                            <span
                              className={`ad-rank ${index === 0 ? 'ad-rank-first' : ''}`}
                            >
                              {index + 1}
                            </span>
                            <div>
                              <div className="ad-item-title">
                                <strong>{item.name}</strong>
                                <span>
                                  {formatNumber(item.quantity)}{' '}
                                  <small>{item.uom ?? 'item'}</small>
                                </span>
                              </div>
                              <div className="ad-progress">
                                <span
                                  style={{
                                    width: `${(item.quantity / Math.max(stats.top_items[0].quantity, 1)) * 100}%`,
                                  }}
                                />
                              </div>
                              <small>{formatCurrency(item.revenue)}</small>
                            </div>
                          </li>
                        ))}
                      </ol>
                    )}
                  </Panel>
                </div>
              </>
            )
          )}
        </>
      )}
      <section className="ad-shortcuts">
        <div>
          <h2>Akses Cepat</h2>
          <p>Lanjutkan pekerjaan Anda</p>
        </div>
        <div className="ad-shortcut-links">
          {shortcuts.map((item) => (
            <Link to={item.to} key={item.to}>
              <NavigationIcon name={item.icon} />
              <span>{item.label}</span>
            </Link>
          ))}
          {shortcuts.length === 0 && (
            <p>Menu tersedia sesuai izin akun Anda.</p>
          )}
        </div>
      </section>
      {canStock && (
        <div className="ad-grid ad-stock-grid">
          <Panel
            title="Kondisi persediaan"
            description="Stok terkini di unit Anda"
            action={
              <Link className="ad-text-link" to="/inventory">
                Lihat stok
              </Link>
            }
          >
            {inventory.error ? (
              <ErrorState
                error={inventory.error}
                retry={() => void inventory.refetch()}
              />
            ) : inventory.isPending ? (
              <Skeleton rows={3} />
            ) : rows.length === 0 ? (
              <Empty icon="stock" title="Belum ada inventori produk">
                Produk aktif dengan stok akan ditampilkan di sini. Layanan tidak
                memiliki stok.
              </Empty>
            ) : (
              <div className="ad-stock-health">
                <div
                  className="ad-donut"
                  style={{ background: ring }}
                  role="img"
                  aria-label={`${rows.length} produk: ${groups.map((g) => `${g.count} ${g.label}`).join(', ')}${unknown ? `, ${unknown} status belum tersedia` : ''}`}
                >
                  <div>
                    <strong>{formatNumber(rows.length)}</strong>
                    <span>jenis produk</span>
                  </div>
                </div>
                <ul className="ad-legend">
                  {groups.map((group) => (
                    <li key={group.key}>
                      <i style={{ background: group.color }} />
                      <span>{group.label}</span>
                      <strong>{group.count}</strong>
                    </li>
                  ))}
                  {unknown > 0 && (
                    <li>
                      <i />
                      <span>Status belum tersedia</span>
                      <strong>{unknown}</strong>
                    </li>
                  )}
                </ul>
              </div>
            )}
          </Panel>
          <Panel
            title="Perlu perhatian"
            description="Prioritaskan produk habis dan stok rendah"
            action={
              !inventory.isPending &&
              !inventory.error && (
                <span
                  className={`ad-soft-badge ${critical.length ? 'ad-badge-amber' : ''}`}
                >
                  {critical.length} produk
                </span>
              )
            }
          >
            {inventory.error ? (
              <ErrorState
                error={inventory.error}
                retry={() => void inventory.refetch()}
              />
            ) : inventory.isPending ? (
              <Skeleton rows={3} />
            ) : critical.length === 0 ? (
              <Empty
                icon="stock"
                title={
                  rows.length
                    ? 'Tidak ada stok kritis'
                    : 'Belum ada produk untuk dipantau'
                }
              >
                {rows.length
                  ? 'Produk dengan stok rendah atau habis akan muncul di sini.'
                  : 'Ringkasan tersedia setelah katalog produk unit terisi.'}
              </Empty>
            ) : (
              <div className="ad-attention-list">
                {critical.slice(0, 4).map((row) => (
                  <div className="ad-attention-row" key={row.item.id}>
                    <div>
                      <strong>{row.item.name}</strong>
                      <span>
                        Minimum {formatNumber(row.minimum_stock)} {row.item.uom}
                      </span>
                    </div>
                    <div>
                      <strong>
                        {formatNumber(row.available_quantity)}{' '}
                        <small>{row.item.uom ?? 'unit'}</small>
                      </strong>
                      <StockUrgencyIndicator
                        urgency={row.stock_urgency}
                        minimumStock={row.minimum_stock}
                        availableQuantity={row.available_quantity}
                        compact
                      />
                    </div>
                  </div>
                ))}
                <Link className="ad-text-link" to="/inventory">
                  {critical.length > 4
                    ? `Lihat seluruh ${critical.length} produk di inventori`
                    : 'Periksa persediaan'}
                </Link>
              </div>
            )}
          </Panel>
        </div>
      )}
      <div className="ad-grid ad-bottom-grid">
        {canSales && (
          <Panel
            title="Transaksi terbaru"
            description={`Pembayaran terakhir dalam ${days} hari`}
            action={
              <Link className="ad-text-link" to="/transactions?status=PAID">
                Lihat semua
              </Link>
            }
          >
            {sales.error ? (
              <ErrorState
                error={sales.error}
                retry={() => void sales.refetch()}
              />
            ) : sales.isPending ? (
              <Skeleton rows={3} />
            ) : stats && stats.recent_transactions.length === 0 ? (
              <Empty icon="history" title="Belum ada transaksi lunas">
                Mulai transaksi melalui {ticketing ? 'Ticketing' : 'POS'}.
                Pembayaran yang berhasil akan muncul di sini.
              </Empty>
            ) : (
              <div className="ad-table-scroll">
                <table className="ad-table">
                  <thead>
                    <tr>
                      <th>Transaksi</th>
                      <th>Waktu (WIB)</th>
                      <th>Total</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {stats?.recent_transactions.map((transaction) => (
                      <tr key={transaction.id}>
                        <td>
                          <button
                            className="ad-text-link"
                            onClick={() => setTransactionId(transaction.id)}
                          >
                            {transaction.transaction_number}
                          </button>
                          <small>
                            {transaction.payer?.name ??
                              transaction.creator.name}
                          </small>
                        </td>
                        <td>{dateTimeWib(transaction.paid_at)}</td>
                        <td>
                          <strong>{formatCurrency(transaction.total)}</strong>
                        </td>
                        <td>
                          <Badge value="PAID" />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Panel>
        )}
        {canTransfers && (
          <Panel
            title="Transfer masuk"
            description="Pengiriman yang menunggu respons"
            action={
              <Link
                className="ad-text-link"
                to="/stock-transfers?status=PENDING"
              >
                Lihat semua
              </Link>
            }
          >
            {transfers.error ? (
              <ErrorState
                error={transfers.error}
                retry={() => void transfers.refetch()}
              />
            ) : transfers.isPending ? (
              <Skeleton rows={3} />
            ) : transfers.data?.data.length === 0 ? (
              <Empty icon="transfer" title="Tidak ada transfer menunggu">
                Pengiriman stok dari gudang akan muncul di sini untuk diperiksa.
              </Empty>
            ) : (
              <div className="ad-transfers">
                <div className="ad-transfer-notice">
                  <span>{transfers.data?.meta.total}</span>
                  <p>transfer menunggu pemeriksaan Anda</p>
                </div>
                {transfers.data?.data.map((transfer) => (
                  <button
                    className="ad-transfer-row"
                    key={transfer.id}
                    onClick={() => setTransferId(transfer.id)}
                  >
                    <span className="ad-transfer-icon">
                      <NavigationIcon name="item" />
                    </span>
                    <span>
                      <strong>{transfer.transfer_number}</strong>
                      <small>
                        {transfer.source_location.name} ·{' '}
                        {dateTimeWib(transfer.created_at)} WIB
                      </small>
                    </span>
                    <Badge value="PENDING" />
                  </button>
                ))}
              </div>
            )}
          </Panel>
        )}
      </div>
      <footer className="ad-footer">
        <span>Pandansari · {unit?.name ?? 'Dashboard Admin'}</span>
        <span>
          Penjualan berdasarkan pembayaran WIB · Persediaan menampilkan kondisi
          terkini
        </span>
      </footer>
      {transactionId !== undefined && (
        <TransactionDetail
          id={transactionId}
          onClose={() => setTransactionId(undefined)}
        />
      )}
      {transferId !== undefined && (
        <TransferDetail
          id={transferId}
          onClose={() => setTransferId(undefined)}
        />
      )}
    </div>
  )
}
