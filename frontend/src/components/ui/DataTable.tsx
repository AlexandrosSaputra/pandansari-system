import type { ReactNode } from 'react'
import type { Page, PageMeta } from '../../types/api'
import { apiError } from '../../lib/errors'
import { formatNumber } from '../../utils/format'
import {
  Button,
  Card,
  EmptyState,
  ErrorState,
  Select,
  Skeleton,
} from './primitives'

export interface Column<T> {
  title: string
  render: (row: T) => ReactNode
}
export function Table<T>({
  rows,
  columns,
  rowKey,
  empty,
}: {
  rows: T[]
  columns: Column<T>[]
  rowKey: (row: T) => number | string
  empty?: string
}) {
  if (!rows.length) return <EmptyState message={empty} />
  return (
    <div className="table-scroll">
      <table>
        <thead>
          <tr>
            {columns.map((c, i) => (
              <th key={i} scope="col">
                {c.title}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={rowKey(row)}>
              {columns.map((c, i) => (
                <td key={i}>{c.render(row)}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
export function Pagination({
  meta,
  onPage,
  onSize,
  disabled,
}: {
  meta?: PageMeta
  onPage: (page: number) => void
  onSize?: (size: string) => void
  disabled?: boolean
}) {
  if (!meta) return null
  return (
    <div className="pagination">
      <span>
        {meta.from ?? 0}–{meta.to ?? 0} dari {formatNumber(meta.total)}
      </span>
      <div className="flex items-center gap-2">
        {onSize && (
          <Select
            aria-label="Baris per halaman"
            value={meta.per_page}
            onChange={(e) => onSize(e.target.value)}
          >
            {[15, 30, 50, 100].map((n) => (
              <option key={n} value={n}>
                {n} / halaman
              </option>
            ))}
          </Select>
        )}
        <Button
          variant="secondary"
          disabled={disabled || meta.current_page <= 1}
          onClick={() => onPage(meta.current_page - 1)}
        >
          Sebelumnya
        </Button>
        <span className="whitespace-nowrap">
          {meta.current_page} / {meta.last_page}
        </span>
        <Button
          variant="secondary"
          disabled={disabled || meta.current_page >= meta.last_page}
          onClick={() => onPage(meta.current_page + 1)}
        >
          Berikutnya
        </Button>
      </div>
    </div>
  )
}
export function ListContent<T>({
  query,
  columns,
  rowKey,
  empty,
  onPage,
  onSize,
}: {
  query: {
    data?: Page<T>
    isPending: boolean
    isFetching: boolean
    isPlaceholderData: boolean
    error: unknown
    refetch: () => unknown
  }
  columns: Column<T>[]
  rowKey: (row: T) => number | string
  empty?: string
  onPage: (page: number) => void
  onSize?: (size: string) => void
}) {
  if (query.error && [401, 403, 404].includes(apiError(query.error).status)) {
    return (
      <ErrorState
        error={query.error}
        retry={() => {
          void query.refetch()
        }}
      />
    )
  }
  return (
    <Card className="overflow-hidden">
      {!!query.error && (
        <ErrorState
          error={query.error}
          retry={() => {
            void query.refetch()
          }}
        />
      )}
      {query.isPending ? (
        <Skeleton />
      ) : (
        query.data && (
          <>
            <div className="h-1" aria-live="polite">
              {query.isFetching && (
                <div
                  className="h-1 bg-emerald-200"
                  aria-label="Memperbarui data"
                />
              )}
            </div>
            <Table
              rows={query.data.data}
              columns={columns}
              rowKey={rowKey}
              empty={empty}
            />
            <Pagination
              meta={query.data.meta}
              onPage={onPage}
              onSize={onSize}
              disabled={query.isPlaceholderData}
            />
          </>
        )
      )}
    </Card>
  )
}
