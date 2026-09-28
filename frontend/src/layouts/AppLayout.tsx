import { AnimatePresence, LazyMotion, domAnimation, m } from 'motion/react'
import { Outlet, useLocation } from 'react-router-dom'
import { Sidebar } from '../components/common/Sidebar'
import { Topbar } from '../components/common/Topbar'

export function AppLayout() {
  const location = useLocation()

  return (
    <div className="min-h-screen bg-slate-50 text-slate-950">
      <Sidebar />

      <div className="min-h-screen lg:pl-64">
        <Topbar />

        <LazyMotion features={domAnimation}>
          <AnimatePresence initial={false} mode="wait">
            <m.main
              key={location.pathname}
              initial={{ opacity: 0, y: 6 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -4 }}
              transition={{ duration: 0.2, ease: 'easeOut' }}
              className="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8"
            >
              <Outlet />
            </m.main>
          </AnimatePresence>
        </LazyMotion>
      </div>
    </div>
  )
}
