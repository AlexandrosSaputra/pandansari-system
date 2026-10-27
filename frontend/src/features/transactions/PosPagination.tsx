import type { PageMeta } from '../../types/api'
import { formatNumber } from '../../utils/format'

export function PosPagination({
  meta,
  disabled,
  onPage,
}: {
  meta?: PageMeta
  disabled: boolean
  onPage: (page: number) => void
}) {
  if (!meta) return null

  return (
    <footer className="pos-catalog-footer">
      <p className="pos-page-summary" role="status">
        {meta.total > 0 ? (
          <>Menampilkan <strong>{formatNumber(meta.from ?? 0)}–{formatNumber(meta.to ?? 0)}</strong> dari <strong>{formatNumber(meta.total)}</strong> item</>
        ) : 'Tidak ada item'}
      </p>
      <nav className="pos-page-controls" aria-label="Halaman katalog">
        <button
          type="button"
          className="pos-page-button"
          aria-label="Halaman sebelumnya"
          title="Halaman sebelumnya"
          disabled={disabled || meta.current_page <= 1}
          onClick={() => onPage(meta.current_page - 1)}
        >
          <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="m14 6-6 6 6 6" /></svg>
        </button>
        <span className="pos-page-position">
          Halaman <strong aria-current="page">{formatNumber(meta.current_page)}</strong>
          <span>dari {formatNumber(meta.last_page)}</span>
        </span>
        <button
          type="button"
          className="pos-page-button"
          aria-label="Halaman berikutnya"
          title="Halaman berikutnya"
          disabled={disabled || meta.current_page >= meta.last_page}
          onClick={() => onPage(meta.current_page + 1)}
        >
          <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="m10 6 6 6-6 6" /></svg>
        </button>
      </nav>
    </footer>
  )
}
