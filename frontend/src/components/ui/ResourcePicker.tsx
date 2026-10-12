import { useState } from 'react'
import { useList } from '../../lib/queries'
import type { QueryParams } from '../../types/api'
import { Button, ErrorState, Input, Pagination, Skeleton } from './index'

// Options are searched and paged on the server; large masters are never downloaded in full.
export function ResourcePicker<T>({
  path,
  params = {},
  value,
  selectedLabel,
  onChange,
  identify,
  describe,
  enabled = true,
  isOptionDisabled,
}: {
  path: string
  params?: QueryParams
  value?: number
  selectedLabel?: string
  onChange: (row: T) => void
  identify: (row: T) => number
  describe: (row: T) => string
  enabled?: boolean
  isOptionDisabled?: (row: T) => boolean
}) {
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const query = useList<T>(
    path,
    { ...params, ...(search ? { search } : {}), page, per_page: 15 },
    enabled && open,
  )
  return (
    <div className="picker">
      <Button
        variant="secondary"
        disabled={!enabled}
        aria-expanded={open}
        onClick={() => setOpen(!open)}
      >
        {selectedLabel || (value ? `Pilihan #${value}` : 'Pilih…')}{' '}
        <span aria-hidden="true">⌄</span>
      </Button>
      {open && enabled && (
        <div className="picker-options">
          <Input
            autoFocus
            aria-label="Cari pilihan"
            placeholder="Cari pilihan…"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              setPage(1)
            }}
          />
          {query.error && (
            <ErrorState
              error={query.error}
              retry={() => {
                void query.refetch()
              }}
            />
          )}
          {query.isPending ? (
            <Skeleton rows={2} />
          ) : (
            <div className="max-h-52 overflow-auto">
              {query.data?.data.map((row) => (
                <button
                  type="button"
                  className="picker-option"
                  aria-pressed={value === identify(row)}
                  key={identify(row)}
                  disabled={
                    !!query.error ||
                    query.isPlaceholderData ||
                    isOptionDisabled?.(row)
                  }
                  onClick={() => {
                    onChange(row)
                    setOpen(false)
                  }}
                >
                  {describe(row)}
                </button>
              ))}
              {query.data?.data.length === 0 && (
                <p className="p-3 text-sm text-slate-500">Tidak ada pilihan.</p>
              )}
            </div>
          )}
          <Pagination
            meta={query.data?.meta}
            onPage={setPage}
            disabled={query.isPlaceholderData}
          />
        </div>
      )}
    </div>
  )
}
