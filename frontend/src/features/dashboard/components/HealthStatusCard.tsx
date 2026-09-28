import { StatusBadge } from '../../../components/ui/StatusBadge'
import { useHealthQuery } from '../hooks/useHealthQuery'

export function HealthStatusCard() {
  const healthQuery = useHealthQuery()

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
      <div className="flex items-start justify-between gap-4">
        <div>
          <p className="text-sm font-semibold text-slate-900">Laravel API</p>
          <p className="mt-1 text-sm text-slate-500">Koneksi frontend ke health endpoint.</p>
        </div>

        {healthQuery.isSuccess && <StatusBadge label="Terhubung" tone="success" />}
        {healthQuery.isError && <StatusBadge label="Tidak terhubung" tone="danger" />}
      </div>

      <div className="mt-6 border-t border-slate-100 pt-5">
        {healthQuery.isPending && (
          <div aria-label="Memeriksa koneksi API" className="grid gap-2" role="status">
            <div className="h-4 w-32 animate-pulse rounded bg-slate-200" />
            <div className="h-3 w-52 animate-pulse rounded bg-slate-100" />
          </div>
        )}

        {healthQuery.isSuccess && (
          <div className="grid gap-1">
            <p className="text-sm font-medium text-slate-900">
              {healthQuery.data.application}
            </p>
            <p className="text-sm text-slate-500">
              API merespons dengan status{' '}
              <span className="font-medium">{healthQuery.data.status}</span>.
            </p>
          </div>
        )}

        {healthQuery.isError && (
          <div className="grid gap-3" role="alert">
            <div>
              <p className="text-sm font-medium text-rose-700">
                Laravel API belum dapat dijangkau.
              </p>
              <p className="mt-1 text-sm text-slate-500">
                Pastikan backend berjalan dan VITE_API_URL mengarah ke endpoint API yang benar.
              </p>
            </div>
            <button
              type="button"
              onClick={() => healthQuery.refetch()}
              className="w-fit rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
            >
              Coba lagi
            </button>
          </div>
        )}
      </div>
    </section>
  )
}
