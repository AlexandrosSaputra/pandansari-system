import logoKebun from '../../assets/logo-kebun.png'
import { NavLink } from 'react-router-dom'
import { useAuth } from '../../features/auth/useAuth'
import { navigation } from './navigation'
export function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
  const auth = useAuth()
  const items = navigation.filter(
    (item) =>
      auth.hasPermission(item.permission) &&
      (!item.transaction ||
        ['admin', 'superadmin'].includes(auth.user?.role?.slug ?? '')),
  )
  return (
    <div className="sidebar-content">
      <div className="sidebar-brand">
        <img className="brand-logo" src={logoKebun} alt="Logo Kebun Pandansari" />
        <div>
          <p className="font-semibold">Pandansari</p>
          <p className="text-xs text-slate-400">Sistem Operasional</p>
        </div>
      </div>
      <nav
        aria-label="Navigasi utama"
        className="flex-1 overflow-auto px-3 py-5"
      >
        {[...new Set(items.map((item) => item.group))].map((group) => (
          <div className="mb-5" key={group}>
            <p className="nav-group">{group}</p>
            {items
              .filter((item) => item.group === group)
              .map((item) => (
                <NavLink
                  key={item.to}
                  to={item.to}
                  end={item.to === '/inventory'}
                  onClick={onNavigate}
                  className={({ isActive }) =>
                    `nav-item ${isActive ? 'active' : ''}`
                  }
                >
                  <span aria-hidden="true" className="nav-icon">
                    {item.icon}
                  </span>
                  {item.label}
                </NavLink>
              ))}
          </div>
        ))}
      </nav>
      <div className="border-t border-slate-100 p-5 text-xs text-slate-400">
        Pandansari System <span className="float-right">Operasional</span>
      </div>
    </div>
  )
}
