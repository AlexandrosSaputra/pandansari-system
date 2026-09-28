import { NavLink } from 'react-router-dom'

const navigationItems = [{ label: 'Dashboard', to: '/dashboard' }]

export function Sidebar() {
  return (
    <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-slate-200 bg-white lg:flex">
      <div className="flex h-16 items-center gap-3 border-b border-slate-200 px-6">
        <div className="grid size-9 place-items-center rounded-xl bg-emerald-600 text-sm font-bold text-white shadow-sm">
          PS
        </div>
        <div>
          <p className="text-sm font-semibold text-slate-900">Pandansari System</p>
          <p className="text-xs text-slate-500">Application foundation</p>
        </div>
      </div>

      <nav aria-label="Navigasi utama" className="flex-1 px-4 py-6">
        <p className="px-3 text-xs font-semibold tracking-wider text-slate-400 uppercase">
          Menu
        </p>
        <div className="mt-3 grid gap-1">
          {navigationItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              className={({ isActive }) =>
                [
                  'rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                  isActive
                    ? 'bg-emerald-50 text-emerald-700'
                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                ].join(' ')
              }
            >
              {item.label}
            </NavLink>
          ))}
        </div>
      </nav>

      <div className="border-t border-slate-200 px-6 py-4 text-xs text-slate-500">
        Foundation environment
      </div>
    </aside>
  )
}
