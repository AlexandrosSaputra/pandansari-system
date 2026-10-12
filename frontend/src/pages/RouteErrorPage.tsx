import { Link, useRouteError } from 'react-router-dom'
import { ErrorState } from '../components/ui'
export function RouteErrorPage() {
  const error = useRouteError()
  return (
    <main className="mx-auto max-w-lg p-8">
      <h1>Halaman tidak dapat dimuat</h1>
      <ErrorState error={error} />
      <Link to="/dashboard" className="btn btn-secondary">
        Kembali ke Dashboard
      </Link>
    </main>
  )
}
