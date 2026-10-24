import { useState, type ReactNode } from 'react'
import { Button, ErrorState, Skeleton } from '../../components/ui'
import { useFilters } from '../../hooks/useFilters'
import { apiError } from '../../lib/errors'
import { useList } from '../../lib/queries'
import type { Item, PageMeta } from '../../types/api'
import { formatNumber, label } from '../../utils/format'
import { useAuth } from '../auth/useAuth'
import { ItemForm } from './ItemForm'

type IconName = 'box' | 'check' | 'category' | 'building'

function MetricIcon({ name }: { name: IconName }) {
  const paths: Record<IconName, ReactNode> = {
    box: (
      <>
        <path d="m21 8-9 5-9-5" />
        <path d="m3 8 9-5 9 5v8l-9 5-9-5Z" />
        <path d="M12 13v8" />
      </>
    ),
    check: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="m8.5 12 2.2 2.2 4.8-4.8" />
      </>
    ),
    category: (
      <>
        <rect x="4" y="4" width="6" height="6" rx="1.2" />
        <rect x="14" y="4" width="6" height="6" rx="1.2" />
        <rect x="4" y="14" width="6" height="6" rx="1.2" />
        <rect x="14" y="14" width="6" height="6" rx="1.2" />
      </>
    ),
    building: (
      <>
        <path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16" />
        <path d="M3 21h18M9 7h1M14 7h1M9 11h1M14 11h1M10 21v-5h4v5" />
      </>
    ),
  }

  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none">
      {paths[name]}
    </svg>
  )
}

function SearchIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none">
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-4-4" />
    </svg>
  )
}

function ResetIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none">
      <path d="M4 4v5h5M20 20v-5h-5" />
      <path d="M5.5 9a7.5 7.5 0 0 1 12.7-2.7L20 9M18.5 15a7.5 7.5 0 0 1-12.7 2.7L4 15" />
    </svg>
  )
}

function MetricCard({
  tone,
  title,
  value,
  detail,
  icon,
}: {
  tone: 'jade' | 'aqua' | 'herb' | 'violet'
  title: string
  value: string
  detail: string
  icon: IconName
}) {
  return (
    <article className={`items-metric items-metric-${tone}`}>
      <div className="items-metric-heading">
        <span>{title}</span>
        <span className="items-metric-icon">
          <MetricIcon name={icon} />
        </span>
      </div>
      <strong>{value}</strong>
      <span className="items-metric-pill">
        <i />
        {detail}
      </span>
    </article>
  )
}

function ItemsFilters({
  search,
  type,
  status,
  onSet,
  onReset,
}: {
  search: string
  type: string
  status: string
  onSet: (name: string, value: string) => void
  onReset: () => void
}) {
  const [draft, setDraft] = useState(search)

  return (
    <form
      className="items-filter-card"
      onSubmit={(event) => {
        event.preventDefault()
        onSet('search', draft)
      }}
    >
      <label className="items-search-field">
        <span>Pencarian</span>
        <span className="items-search-wrap">
          <SearchIcon />
          <input
            type="search"
            value={draft}
            onChange={(event) => setDraft(event.target.value)}
            onBlur={() => {
              if (draft !== search) onSet('search', draft)
            }}
            placeholder="Cari kode, nama item, atau barcode..."
          />
          <kbd>↵ Enter</kbd>
        </span>
      </label>

      <label>
        <span>Jenis</span>
        <select value={type} onChange={(event) => onSet('type', event.target.value)}>
          <option value="">Semua Jenis</option>
          <option value="PRODUCT">Produk</option>
          <option value="SERVICE">Layanan</option>
        </select>
      </label>

      <label>
        <span>Status</span>
        <select
          value={status}
          onChange={(event) => onSet('is_active', event.target.value)}
        >
          <option value="">Semua Status</option>
          <option value="1">Aktif</option>
          <option value="0">Nonaktif</option>
        </select>
      </label>

      <button type="button" className="items-reset-button" onClick={onReset}>
        <ResetIcon />
        Reset filter
      </button>
    </form>
  )
}

function pageItems(meta: PageMeta) {
  const { current_page: current, last_page: last } = meta
  if (last <= 5) return Array.from({ length: last }, (_, index) => index + 1)

  const pages = new Set([1, last, current - 1, current, current + 1])
  return [...pages].filter((page) => page > 0 && page <= last).sort((a, b) => a - b)
}

function ItemsTable({
  rows,
  meta,
  manage,
  busy,
  onEdit,
  onPage,
  onSize,
}: {
  rows: Item[]
  meta: PageMeta
  manage: boolean
  busy: boolean
  onEdit: (item: Item) => void
  onPage: (page: number) => void
  onSize: (size: string) => void
}) {
  const pages = pageItems(meta)

  return (
    <section className="items-table-card">
      {busy && <div className="items-table-progress" aria-label="Memperbarui data" />}
      {rows.length ? (
        <div className="items-table-scroll">
          <table className="items-table">
            <thead>
              <tr>
                <th className="items-number-column">No</th>
                <th>Kode</th>
                <th>Barcode</th>
                <th>Nama item</th>
                <th>Jenis</th>
                <th className="items-centered">Satuan</th>
                <th className="items-centered">Faktor pengunjung</th>
                <th>Status</th>
                <th className="items-action-column">Aksi</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row, index) => (
                <tr key={row.id}>
                  <td className="items-number-column">
                    {(meta.current_page - 1) * meta.per_page + index + 1}
                  </td>
                  <td className="items-code">{row.item_code}</td>
                  <td className="items-barcode">{row.barcode ?? '—'}</td>
                  <td className="items-name">{row.name}</td>
                  <td>
                    <span className="items-type-badge">{label(row.type)}</span>
                  </td>
                  <td className="items-centered">{row.uom ?? '—'}</td>
                  <td className="items-centered">{row.visitor_factor}</td>
                  <td>
                    <span className={`items-status-badge ${row.is_active ? 'is-active' : 'is-inactive'}`}>
                      <i />
                      {row.is_active ? 'Aktif' : 'Nonaktif'}
                    </span>
                  </td>
                  <td className="items-action-column">
                    {manage ? (
                      <button type="button" onClick={() => onEdit(row)}>
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none">
                          <path d="M12 20h9" />
                          <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z" />
                        </svg>
                        Edit
                      </button>
                    ) : (
                      '—'
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="items-empty-state">
          <span>—</span>
          <p>Belum ada item yang sesuai.</p>
        </div>
      )}

      <footer className="items-pagination">
        <p>
          Menampilkan <strong>{meta.from ?? 0}</strong> sampai{' '}
          <strong>{meta.to ?? 0}</strong> dari <strong>{formatNumber(meta.total)}</strong> data
        </p>
        <div className="items-pagination-controls">
          <select
            aria-label="Jumlah baris per halaman"
            value={meta.per_page}
            onChange={(event) => onSize(event.target.value)}
          >
            {[15, 30, 50, 100].map((size) => (
              <option key={size} value={size}>
                {size} / halaman
              </option>
            ))}
          </select>
          <nav aria-label="Navigasi halaman">
            <button
              type="button"
              disabled={busy || meta.current_page <= 1}
              onClick={() => onPage(meta.current_page - 1)}
            >
              Sebelumnya
            </button>
            {pages.map((page, index) => {
              const previous = pages[index - 1]
              return (
                <span key={page} className="contents">
                  {previous && page - previous > 1 && <i aria-hidden="true">…</i>}
                  <button
                    type="button"
                    className={page === meta.current_page ? 'is-current' : 'items-page-number'}
                    aria-current={page === meta.current_page ? 'page' : undefined}
                    disabled={busy}
                    onClick={() => onPage(page)}
                  >
                    {page}
                  </button>
                </span>
              )
            })}
            <button
              type="button"
              disabled={busy || meta.current_page >= meta.last_page}
              onClick={() => onPage(meta.current_page + 1)}
            >
              Berikutnya
            </button>
          </nav>
        </div>
      </footer>
    </section>
  )
}

export function ItemsPage() {
  const auth = useAuth()
  const filters = useFilters()
  const query = useList<Item>('/items', filters.params)
  const totalQuery = useList<Item>('/items', { per_page: 1 })
  const activeQuery = useList<Item>('/items', { per_page: 1, is_active: true })
  const [editing, setEditing] = useState<Item | 'new' | null>(null)
  const manage = auth.isSuperadmin && auth.hasPermission('item.manage')
  const protectedError = query.error && [401, 403, 404].includes(apiError(query.error).status)
  const total = totalQuery.data?.meta.total
  const active = activeQuery.data?.meta.total
  const activeRate = total && active != null ? Math.round((active / total) * 100) : null

  return (
    <div className="items-page">
      <header className="items-page-header">
        <div>
          <h1>Master Item</h1>
          <p>Kelola data referensi produk, layanan, dan inventaris untuk seluruh outlet dan sistem ticketing.</p>
        </div>
        {manage && (
          <Button className="items-add-button" onClick={() => setEditing('new')}>
            <span aria-hidden="true">+</span>
            Tambah Item
          </Button>
        )}
      </header>

      <section className="items-metrics" aria-label="Ringkasan master item">
        <MetricCard
          tone="jade"
          title="Total item"
          value={total == null ? '—' : formatNumber(total)}
          detail="Katalog master"
          icon="box"
        />
        <MetricCard
          tone="herb"
          title="Item aktif"
          value={active == null ? '—' : formatNumber(active)}
          detail={activeRate == null ? 'Status aktif' : `${activeRate}% aktif`}
          icon="check"
        />
        <MetricCard
          tone="aqua"
          title="Kategori item"
          value="2"
          detail="Produk & layanan"
          icon="category"
        />
        <MetricCard
          tone="violet"
          title="Cakupan outlet"
          value="Semua"
          detail="Katalog global"
          icon="building"
        />
      </section>

      <ItemsFilters
        key={filters.value('search')}
        search={filters.value('search')}
        type={filters.value('type')}
        status={filters.value('is_active')}
        onSet={filters.set}
        onReset={filters.reset}
      />

      {protectedError ? (
        <ErrorState error={query.error} retry={() => void query.refetch()} />
      ) : query.isPending ? (
        <section className="items-table-card">
          <Skeleton rows={10} />
        </section>
      ) : (
        query.data && (
          <>
            {!!query.error && <ErrorState error={query.error} retry={() => void query.refetch()} />}
            <ItemsTable
              rows={query.data.data}
              meta={query.data.meta}
              manage={manage}
              busy={query.isFetching || query.isPlaceholderData}
              onEdit={setEditing}
              onPage={(page) => filters.set('page', String(page))}
              onSize={(size) => filters.set('per_page', size)}
            />
          </>
        )
      )}

      {editing && (
        <ItemForm
          item={editing === 'new' ? undefined : editing}
          onClose={() => setEditing(null)}
          onSaved={() => setEditing(null)}
        />
      )}
    </div>
  )
}
