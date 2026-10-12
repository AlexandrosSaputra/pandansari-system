import { Skeleton } from '../ui'

export function RouterHydrationFallback() {
  return (
    <div
      role="status"
      aria-label="Memuat aplikasi"
      className="grid min-h-dvh place-items-center bg-slate-50 p-6"
    >
      <div className="w-full max-w-sm rounded-xl border border-slate-200 bg-white shadow-sm">
        <Skeleton rows={4} />
      </div>
    </div>
  )
}
