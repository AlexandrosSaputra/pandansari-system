import { Link } from 'react-router-dom'
export function ForbiddenPage() {
  return (
    <section className="grid min-h-[60vh] place-items-center">
      <div className="max-w-md text-center">
        <p className="eyebrow">403 · AKSES TERBATAS</p>
        <h1 className="mt-3">Akses tidak diizinkan</h1>
        <p className="my-4 text-slate-500">
          Anda tidak memiliki izin untuk mengakses halaman ini.
        </p>
        <Link className="btn btn-primary" to="/dashboard">
          Kembali ke Dashboard
        </Link>
      </div>
    </section>
  )
}
