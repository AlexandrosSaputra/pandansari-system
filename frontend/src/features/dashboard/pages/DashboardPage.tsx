import { HealthStatusCard } from '../components/HealthStatusCard'

export function DashboardPage() {
  return (
    <div className="grid gap-6">
      <div>
        <p className="text-sm font-semibold text-emerald-700">Foundation</p>
        <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">
          Dashboard
        </h1>
        <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
          Fondasi aplikasi siap digunakan untuk pengembangan modul berikutnya.
        </p>
      </div>

      <div className="max-w-xl">
        <HealthStatusCard />
      </div>
    </div>
  )
}
