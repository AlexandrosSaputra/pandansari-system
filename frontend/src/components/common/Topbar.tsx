import { Link, useLocation } from 'react-router-dom'
import { useAuth } from '../../features/auth/useAuth'
import { Button, ErrorState } from '../ui'
import { navigation } from './navigation'

function ChevronIcon() {
  return (
    <svg
      aria-hidden="true"
      className="size-3 text-slate-300"
      fill="none"
      stroke="currentColor"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth="2"
      viewBox="0 0 24 24"
    >
      <path d="m9 5 7 7-7 7" />
    </svg>
  )
}

function ProfileMenuIcon({ name }: { name: 'profile' | 'settings' | 'log' | 'logout' }) {
  const content = {
    profile: (
      <>
        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
        <circle cx="12" cy="7" r="4" />
      </>
    ),
    settings: (
      <>
        <circle cx="12" cy="12" r="3" />
        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09A1.65 1.65 0 0 0 19.4 15Z" />
      </>
    ),
    log: (
      <>
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
        <path d="M14 2v6h6M8 13h8M8 17h8" />
      </>
    ),
    logout: (
      <>
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
        <path d="m16 17 5-5-5-5M21 12H9" />
      </>
    ),
  }[name]

  return (
    <svg
      aria-hidden="true"
      className="size-5 flex-none"
      fill="none"
      stroke="currentColor"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth="1.8"
      viewBox="0 0 24 24"
    >
      {content}
    </svg>
  )
}

export function Topbar({ onMenu }: { onMenu: () => void }) {
  const auth = useAuth()
  const { pathname } = useLocation()
  const current = [...navigation]
    .sort((a, b) => b.to.length - a.to.length)
    .find((item) => pathname === item.to || pathname.startsWith(`${item.to}/`))
  const currentLabel =
    current?.to === '/dashboard'
      ? 'Ringkasan Operasional'
      : (current?.label ?? 'Pandansari')
  const userName = auth.user?.name ?? 'Pengguna'
  const roleName = auth.user?.role?.name ?? 'Akun'
  const unitName =
    auth.user?.operational_unit?.name ??
    (auth.canWarehouse ? 'Gudang Utama' : 'Semua unit')

  return (
    <header className="topbar">
      <div className="flex min-w-0 items-center gap-3 sm:gap-5">
        <button
          type="button"
          className="topbar-menu-button lg:hidden"
          aria-label="Buka navigasi"
          onClick={onMenu}
        >
          <svg
            aria-hidden="true"
            className="size-5"
            fill="none"
            stroke="currentColor"
            strokeLinecap="round"
            strokeWidth="2"
            viewBox="0 0 24 24"
          >
            <path d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>

        <nav aria-label="Breadcrumb" className="topbar-breadcrumb">
          {current?.group && current.group !== 'Ruang kerja' && (
            <>
              <span className="text-slate-500">{current.group}</span>
              <ChevronIcon />
            </>
          )}
          <span className="topbar-current">
            <span aria-hidden="true" className="topbar-current-dot" />
            <span className="truncate">{currentLabel}</span>
          </span>
        </nav>
      </div>

      <div className="flex flex-none items-center gap-2 sm:gap-3">
        <button
          type="button"
          className="notification-button"
          aria-label="Notifikasi"
          title="Notifikasi"
        >
          <svg
            aria-hidden="true"
            className="size-5"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            viewBox="0 0 24 24"
          >
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
          </svg>
          <span aria-hidden="true" className="notification-dot" />
        </button>
        <span aria-hidden="true" className="topbar-divider" />

        <details className="profile-details">
          <summary className="profile-summary">
            <span className="profile-avatar">{userName.slice(0, 1)}</span>
            <span className="hidden min-w-0 flex-col text-left sm:flex">
              <span className="max-w-32 truncate text-xs font-bold leading-tight text-slate-800">
                {userName}
              </span>
              <span className="mt-0.5 max-w-32 truncate text-[11px] leading-tight text-slate-400">
                {roleName}
              </span>
            </span>
            <svg
              aria-hidden="true"
              className="size-4 flex-none text-slate-400 transition-transform"
              fill="none"
              stroke="currentColor"
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth="2"
              viewBox="0 0 24 24"
            >
              <path d="m6 9 6 6 6-6" />
            </svg>
          </summary>

          <div className="profile-menu">
            <div className="profile-card">
              <div className="flex items-center gap-2.5">
                <span className="profile-avatar flex-none">
                  {userName.slice(0, 1)}
                </span>
                <div className="min-w-0 flex-1">
                  <div className="flex items-center justify-between gap-2">
                    <p className="truncate text-xs font-bold text-slate-800">
                      {userName}
                    </p>
                    <span className="rounded-md bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">
                      {roleName}
                    </span>
                  </div>
                  <p className="mt-0.5 truncate text-[11px] text-slate-500">
                    {auth.user?.email}
                  </p>
                </div>
              </div>
              <div className="mt-2.5 flex items-center justify-between border-t border-slate-200/60 pt-2 text-[11px]">
                <span className="font-medium text-slate-400">Unit Aktif:</span>
                <span className="inline-flex min-w-0 items-center gap-1.5 font-semibold text-slate-700">
                  <span className="size-2 flex-none rounded-full bg-emerald-500 ring-2 ring-emerald-100" />
                  {(auth.user?.assignments.length ?? 0) > 1 ? (
                    <label className="profile-assignment">
                      <span className="sr-only">Ganti ruang kerja</span>
                      <select
                        className="profile-assignment-select"
                        value={auth.user?.active_assignment?.id ?? ''}
                        disabled={auth.assignmentMutation.isPending}
                        onChange={(event) => {
                          void auth.selectAssignment(Number(event.target.value))
                        }}
                      >
                        {auth.user?.assignments.map((assignment) => (
                          <option key={assignment.id} value={assignment.id}>
                            {assignment.operational_unit?.name ??
                              assignment.role.name}
                          </option>
                        ))}
                      </select>
                    </label>
                  ) : (
                    <span className="max-w-32 truncate">{unitName}</span>
                  )}
                </span>
              </div>
            </div>

            <div className="profile-actions">
              <button
                type="button"
                className="profile-action"
                disabled
                title="Halaman profil belum tersedia"
              >
                <ProfileMenuIcon name="profile" />
                <span>Profil Saya</span>
              </button>
              <button
                type="button"
                className="profile-action"
                disabled
                title="Pengaturan akun belum tersedia"
              >
                <ProfileMenuIcon name="settings" />
                <span>Pengaturan Akun</span>
              </button>
              {auth.hasPermission('audit.view') && (
                <Link className="profile-action" to="/activity-logs">
                  <ProfileMenuIcon name="log" />
                  <span>Log Aktivitas Saya</span>
                </Link>
              )}
            </div>

            {auth.logoutMutation.error && (
              <ErrorState error={auth.logoutMutation.error} />
            )}
            <Button
              variant="ghost"
              className="profile-logout"
              busy={auth.logoutMutation.isPending}
              onClick={() => {
                void auth.logout().catch(() => undefined)
              }}
            >
              <ProfileMenuIcon name="logout" />
              Keluar / Log out
            </Button>
          </div>
        </details>
      </div>
    </header>
  )
}
