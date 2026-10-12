import { useLocation } from 'react-router-dom'
import { useAuth } from '../../features/auth/useAuth'
import { Button, ErrorState } from '../ui'
import { navigation } from './navigation'
export function Topbar({ onMenu }: { onMenu: () => void }) {
  const auth = useAuth()
  const { pathname } = useLocation()
  const current = [...navigation]
    .reverse()
    .find((item) => pathname.startsWith(item.to))
  return (
    <header className="topbar">
      <div className="flex items-center gap-3">
        <Button
          variant="ghost"
          className="lg:hidden"
          aria-label="Buka navigasi"
          onClick={onMenu}
        >
          ☰
        </Button>
        <div>
          <p className="text-xs text-slate-400">Ruang kerja /</p>
          <p className="text-sm font-semibold">
            {current?.label ?? 'Pandansari'}
          </p>
        </div>
      </div>
      <div className="flex items-center gap-4">
        <span className="hidden text-sm text-slate-500 md:block">
          {auth.user?.operational_unit?.name ??
            (auth.canWarehouse ? 'Gudang Utama' : '')}
        </span>
        <details className="relative">
          <summary className="flex cursor-pointer list-none items-center gap-3 rounded-lg p-1">
            <span className="grid size-9 place-items-center rounded-full bg-emerald-50 font-semibold text-emerald-800">
              {auth.user?.name.slice(0, 1)}
            </span>
            <span className="hidden text-sm sm:block">
              {auth.user?.name}
              <small className="block text-xs text-slate-500">
                {auth.user?.role?.name}
              </small>
            </span>
            <span aria-hidden="true">⌄</span>
          </summary>
          <div className="profile-menu">
            <p className="mb-1 font-semibold">{auth.user?.name}</p>
            <p className="mb-4 break-all text-xs text-slate-500">
              {auth.user?.email}
            </p>
            {auth.logoutMutation.error && (
              <ErrorState error={auth.logoutMutation.error} />
            )}
            <Button
              variant="secondary"
              className="w-full"
              busy={auth.logoutMutation.isPending}
              onClick={() => {
                void auth.logout().catch(() => undefined)
              }}
            >
              Keluar
            </Button>
          </div>
        </details>
      </div>
    </header>
  )
}
