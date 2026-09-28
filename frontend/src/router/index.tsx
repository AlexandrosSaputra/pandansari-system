import { createBrowserRouter, Navigate } from 'react-router-dom'
import { DashboardPage } from '../features/dashboard/pages/DashboardPage'
import { AppLayout } from '../layouts/AppLayout'
import { NotFoundPage } from '../pages/NotFoundPage'

export const router = createBrowserRouter([
  {
    element: <AppLayout />,
    children: [
      {
        index: true,
        element: <Navigate to="/dashboard" replace />,
      },
      {
        path: 'dashboard',
        element: <DashboardPage />,
      },
      {
        path: '*',
        element: <NotFoundPage />,
      },
    ],
  },
])
