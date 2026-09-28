import { Link } from 'react-router-dom'

export function NotFoundPage() {
  return (
    <section className="grid min-h-[60vh] place-items-center">
      <div className="max-w-md text-center">
        <p className="text-sm font-semibold text-emerald-700">404</p>
        <h1 className="mt-2 text-3xl font-bold tracking-tight text-slate-950">
          Halaman tidak ditemukan
        </h1>
        <p className="mt-3 text-sm leading-6 text-slate-600">
          Alamat yang Anda buka tidak tersedia di Pandansari System.
        </p>
        <Link
          to="/dashboard"
          className="mt-6 inline-flex rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
        >
          Kembali ke Dashboard
        </Link>
      </div>
    </section>
  )
}
