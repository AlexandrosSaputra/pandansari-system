import { useState } from 'react'
import { m } from 'motion/react'
import { Outlet, useLocation } from 'react-router-dom'
import { Sidebar } from '../components/common/Sidebar'
import { Topbar } from '../components/common/Topbar'
import { Drawer } from '../components/ui'
export function AppLayout() {
  const { pathname } = useLocation()
  const [drawer, setDrawer] = useState(false)
  return (
    <div className="min-h-screen">
      <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 border-r border-slate-200 bg-white lg:block">
        <Sidebar />
      </aside>
      {drawer && (
        <Drawer title="Navigasi" onClose={() => setDrawer(false)}>
          <Sidebar onNavigate={() => setDrawer(false)} />
        </Drawer>
      )}
      <div className="min-h-screen lg:pl-64">
        <Topbar onMenu={() => setDrawer(true)} />
        <m.main
          key={pathname}
          initial={{ opacity: 0, y: 5 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.18 }}
          className="mx-auto max-w-[1600px] p-4 sm:p-6 lg:p-8"
        >
          <Outlet />
        </m.main>
      </div>
    </div>
  )
}
