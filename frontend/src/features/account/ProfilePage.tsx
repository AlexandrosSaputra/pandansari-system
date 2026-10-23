import { Link } from 'react-router-dom'
import { Badge, Card, PageHeader } from '../../components/ui'
import { useAuth } from '../auth/useAuth'

export function ProfilePage() {
  const auth = useAuth()
  const user = auth.user
  const activeAssignment = user?.active_assignment

  if (!user) return null

  return (
    <>
      <PageHeader
        title="Profil Saya"
        description="Informasi identitas dan cakupan akses akun Anda."
        actions={
          <Link className="btn btn-primary" to="/account/settings">
            Edit profil
          </Link>
        }
      />

      <div className="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]">
        <Card className="overflow-hidden">
          <div className="flex flex-col gap-5 border-b border-slate-100 p-6 sm:flex-row sm:items-center">
            <span className="grid size-16 flex-none place-items-center rounded-full bg-emerald-600 text-xl font-bold text-white ring-4 ring-emerald-100">
              {user.name.slice(0, 1).toUpperCase()}
            </span>
            <div className="min-w-0 flex-1">
              <div className="flex flex-wrap items-center gap-2">
                <h2 className="truncate text-lg">{user.name}</h2>
                <Badge value={user.is_active ? 'Aktif' : 'Nonaktif'} />
              </div>
              <p className="mt-1 text-sm text-slate-500">{user.email}</p>
            </div>
          </div>
          <dl className="grid gap-x-6 gap-y-5 p-6 sm:grid-cols-2">
            <ProfileField label="Nama lengkap" value={user.name} />
            <ProfileField label="Username" value={`@${user.username}`} />
            <ProfileField label="Email" value={user.email} />
            <ProfileField
              label="Status akun"
              value={user.is_active ? 'Aktif' : 'Nonaktif'}
            />
          </dl>
        </Card>

        <Card className="p-6">
          <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
            Akses aktif
          </p>
          <h2 className="mt-2">{activeAssignment?.role.name ?? 'Belum dipilih'}</h2>
          <p className="mt-1 text-sm text-slate-500">
            {activeAssignment?.operational_unit?.name ?? 'Akses global sistem'}
          </p>

          <div className="mt-6 border-t border-slate-100 pt-5">
            <p className="mb-3 text-xs font-bold uppercase tracking-wider text-slate-400">
              Seluruh role dan unit
            </p>
            <div className="space-y-2">
              {user.assignments.map((assignment) => (
                <div
                  key={assignment.id}
                  className={`flex items-center justify-between gap-3 rounded-xl border p-3 ${
                    assignment.id === activeAssignment?.id
                      ? 'border-emerald-200 bg-emerald-50'
                      : 'border-slate-200 bg-slate-50'
                  }`}
                >
                  <div className="min-w-0">
                    <p className="text-sm font-semibold text-slate-800">
                      {assignment.role.name}
                    </p>
                    <p className="truncate text-xs text-slate-500">
                      {assignment.operational_unit?.name ?? 'Akses global'}
                    </p>
                  </div>
                  {assignment.id === activeAssignment?.id && (
                    <span className="badge badge-success">Aktif</span>
                  )}
                </div>
              ))}
            </div>
          </div>
        </Card>
      </div>
    </>
  )
}

function ProfileField({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-xs font-medium text-slate-400">{label}</dt>
      <dd className="mt-1 break-words text-sm font-semibold text-slate-800">
        {value}
      </dd>
    </div>
  )
}
