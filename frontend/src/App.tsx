import { QueryClientProvider } from '@tanstack/react-query'
import { RouterProvider } from 'react-router-dom'
import { queryClient } from './lib/queryClient'
import { router } from './router'
import { LazyMotion, MotionConfig } from 'motion/react'
import { ToastNotifications } from './components/common/ToastNotifications'
const loadMotion = () =>
  import('./lib/motionFeatures').then((module) => module.default)

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <MotionConfig reducedMotion="user">
        <LazyMotion features={loadMotion} strict>
          <RouterProvider router={router} />
          <ToastNotifications />
        </LazyMotion>
      </MotionConfig>
    </QueryClientProvider>
  )
}

export default App
