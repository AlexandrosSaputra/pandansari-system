import { useEffect, useState } from 'react'
import { m } from 'motion/react'
import { Outlet, useLocation } from 'react-router-dom'
import { Sidebar } from '../components/common/Sidebar'
import { Topbar } from '../components/common/Topbar'
import {
  TopbarOverrideProvider,
} from '../components/common/TopbarContext'
import type { TopbarOverride } from '../components/common/topbarOverride'
import { Drawer } from '../components/ui'
export function AppLayout() {
  const { pathname } = useLocation()
  const [drawer, setDrawer] = useState(false)
  const [topbarOverride, setTopbarOverride] = useState<TopbarOverride>()
  const [sidebarCollapsed, setSidebarCollapsed] = useState(() =>
    typeof window === 'undefined'
      ? false
      : window.localStorage.getItem('pandansari.sidebar.collapsed') === 'true',
  )

  useEffect(() => {
    window.localStorage.setItem(
      'pandansari.sidebar.collapsed',
      String(sidebarCollapsed),
    )
  }, [sidebarCollapsed])

  return (
    <div
      className={`app-shell min-h-screen ${sidebarCollapsed ? 'sidebar-collapsed' : ''}`}
    >
      <aside className="desktop-sidebar">
        <Sidebar
          collapsed={sidebarCollapsed}
          onToggleCollapse={() => setSidebarCollapsed((value) => !value)}
        />
      </aside>
      {drawer && (
        <Drawer title="Navigasi" onClose={() => setDrawer(false)}>
          <Sidebar onNavigate={() => setDrawer(false)} />
        </Drawer>
      )}
      <div className="app-main min-h-screen">
        <Topbar
          override={topbarOverride}
          onMenu={() => setDrawer(true)}
        />
        <m.main
          key={pathname}
          initial={{ opacity: 0, y: 5 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.18 }}
          className="mx-auto max-w-[1600px] p-4 sm:p-6 lg:p-8"
        >
          <TopbarOverrideProvider setOverride={setTopbarOverride}>
            <Outlet />
          </TopbarOverrideProvider>
        </m.main>
      </div>
    </div>
  )
}
