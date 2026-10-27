import { useEffect, useMemo, useState } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import logoKebun from '../../assets/logo-kebun.png'
import { useAuth } from '../../features/auth/useAuth'
import { NavigationIcon } from './NavigationIcon'
import {
  isNavigationItemVisible,
  navigation,
  type NavigationItem,
} from './navigation'
import { TransactionUnitMenu } from './TransactionUnitMenu'

interface SidebarProps {
  collapsed?: boolean
  onNavigate?: () => void
  onToggleCollapse?: () => void
}

function PanelIcon() {
  return (
    <svg
      aria-hidden="true"
      className="size-4"
      fill="none"
      stroke="currentColor"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth="2"
      viewBox="0 0 24 24"
    >
      <rect x="3" y="3" width="18" height="18" rx="2" />
      <path d="M9 3v18" />
      <path d="m15 9-3 3 3 3" />
    </svg>
  )
}

function NavigationLink({
  item,
  collapsed,
  chooseUnit,
  expanded,
  selectedUnitId,
  onToggleUnits,
  onNavigate,
}: {
  item: NavigationItem
  collapsed: boolean
  chooseUnit: boolean
  expanded: boolean
  selectedUnitId?: number
  onToggleUnits: () => void
  onNavigate?: () => void
}) {
  return (
    <>
      <NavLink
        to={item.to}
        end={item.to === '/dashboard' || item.to === '/inventory'}
        title={collapsed ? item.label : undefined}
        aria-label={
          chooseUnit
            ? `${item.label}, ${expanded ? 'sembunyikan' : 'tampilkan'} pilihan unit`
            : undefined
        }
        aria-expanded={chooseUnit ? expanded : undefined}
        aria-controls={
          chooseUnit ? `transaction-units-${item.to.slice(1)}` : undefined
        }
        onClick={(event) => {
          if (chooseUnit) {
            event.preventDefault()
            onToggleUnits()
            return
          }
          onNavigate?.()
        }}
        className={({ isActive }) =>
          `nav-item ${chooseUnit ? 'has-unit-menu' : ''} ${isActive ? 'active' : ''}`
        }
      >
        <NavigationIcon name={item.icon} className="nav-icon" />
        <span className="sidebar-copy min-w-0 flex-1 truncate">
          {item.label}
        </span>
        {chooseUnit && (
          <span className="sidebar-copy sidebar-menu-toggle" aria-hidden="true">
            <svg
              className="sidebar-menu-indicator"
              fill="none"
              stroke="currentColor"
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth="2"
              viewBox="0 0 24 24"
            >
              <path d="m9 18 6-6-6-6" />
            </svg>
          </span>
        )}
      </NavLink>
      {expanded && chooseUnit && !collapsed && (
        <TransactionUnitMenu
          item={item}
          selectedUnitId={selectedUnitId}
          onNavigate={onNavigate}
        />
      )}
    </>
  )
}

export function Sidebar({
  collapsed = false,
  onNavigate,
  onToggleCollapse,
}: SidebarProps) {
  const auth = useAuth()
  const location = useLocation()
  const [query, setQuery] = useState('')
  const visibleItems = navigation.filter((item) =>
    isNavigationItemVisible(item, {
      hasPermission: auth.hasPermission,
      roleSlug: auth.user?.role?.slug,
      unitType: auth.user?.operational_unit?.type,
    }),
  )
  const items = useMemo(() => {
    const normalizedQuery = query.trim().toLocaleLowerCase('id')
    if (!normalizedQuery) return visibleItems

    return visibleItems.filter((item) =>
      `${item.group} ${item.label}`
        .toLocaleLowerCase('id')
        .includes(normalizedQuery),
    )
  }, [query, visibleItems])
  const groups = [...new Set(items.map((item) => item.group))]
  const [expandedWorkspaces, setExpandedWorkspaces] = useState<
    Record<string, boolean>
  >({})

  useEffect(() => {
    if (!auth.user) setExpandedWorkspaces({})
  }, [auth.user])

  const brand = (
    <>
      <img
        className="brand-logo"
        src={logoKebun}
        alt="Logo Kebun Pandansari"
      />
      <div className="sidebar-copy min-w-0">
        <p className="truncate text-sm font-bold leading-tight text-slate-900">
          Pandansari
        </p>
        <p className="mt-0.5 truncate text-[10px] font-medium leading-none text-slate-400">
          Sistem Operasional
        </p>
      </div>
    </>
  )

  return (
    <div className={`sidebar-content ${collapsed ? 'is-collapsed' : ''}`}>
      <div className="sidebar-brand">
        {collapsed && onToggleCollapse ? (
          <button
            type="button"
            className="sidebar-brand-main sidebar-brand-trigger"
            aria-label="Perluas sidebar"
            title="Perluas sidebar"
            onClick={onToggleCollapse}
          >
            {brand}
          </button>
        ) : (
          <div className="sidebar-brand-main">{brand}</div>
        )}
        {onToggleCollapse && !collapsed && (
          <button
            type="button"
            className="sidebar-collapse-button"
            aria-label="Ciutkan sidebar"
            title="Ciutkan sidebar"
            onClick={onToggleCollapse}
          >
            <PanelIcon />
          </button>
        )}
      </div>

      {!collapsed && (
        <label className="sidebar-search">
          <span className="sr-only">Cari menu</span>
          <svg
            aria-hidden="true"
            className="size-3.5"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            viewBox="0 0 24 24"
          >
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.35-4.35" />
          </svg>
          <input
            type="search"
            value={query}
            placeholder="Cari menu..."
            onChange={(event) => setQuery(event.target.value)}
          />
        </label>
      )}

      <nav aria-label="Navigasi utama" className="sidebar-nav">
        {groups.map((group) => (
          <div className="sidebar-group" key={group}>
            <p className="nav-group">{group}</p>
            <div className="sidebar-group-items">
              {items
                .filter((item) => item.group === group)
                .map((item) => {
                  const chooseUnit = auth.isSuperadmin && !!item.workspaceType
                  const unitPath = location.pathname.startsWith(`${item.to}/`)
                    ? location.pathname
                        .slice(item.to.length + 1)
                        .split('/')[0]
                    : ''

                  return (
                    <NavigationLink
                      key={item.to}
                      item={item}
                      collapsed={collapsed}
                      chooseUnit={chooseUnit}
                      expanded={chooseUnit && !!expandedWorkspaces[item.to]}
                      selectedUnitId={Number(unitPath) || undefined}
                      onToggleUnits={() => {
                        if (collapsed) onToggleCollapse?.()
                        setExpandedWorkspaces((workspaces) => ({
                          ...workspaces,
                          [item.to]: !workspaces[item.to],
                        }))
                      }}
                      onNavigate={onNavigate}
                    />
                  )
                })}
            </div>
          </div>
        ))}
        {!items.length && !collapsed && (
          <p className="px-3 py-6 text-center text-xs text-slate-400">
            Menu tidak ditemukan.
          </p>
        )}
      </nav>

    </div>
  )
}
