import { createBrowserRouter, Navigate } from 'react-router-dom'
import { PermissionGuard, ProtectedRoute } from '../features/auth/RouteGuard'
import { AppLayout } from '../layouts/AppLayout'
import { RouterHydrationFallback } from '../components/common/RouterHydrationFallback'
import { NotFoundPage } from '../pages/NotFoundPage'
import { RouteErrorPage } from '../pages/RouteErrorPage'

export const router = createBrowserRouter([
  {
    path: '/login',
    hydrateFallbackElement: <RouterHydrationFallback />,
    lazy: async () => ({
      Component: (await import('../features/auth/LoginPage')).LoginPage,
    }),
    errorElement: <RouteErrorPage />,
  },
  {
    path: '/forgot-password',
    hydrateFallbackElement: <RouterHydrationFallback />,
    lazy: async () => ({
      Component: (await import('../features/auth/ForgotPasswordPage'))
        .ForgotPasswordPage,
    }),
    errorElement: <RouteErrorPage />,
  },
  {
    element: <ProtectedRoute />,
    hydrateFallbackElement: <RouterHydrationFallback />,
    errorElement: <RouteErrorPage />,
    children: [
      {
        element: <AppLayout />,
        children: [
          { index: true, element: <Navigate to="/dashboard" replace /> },
          {
            element: <PermissionGuard permission="dashboard.view" />,
            children: [
              {
                path: 'dashboard',
                lazy: async () => ({
                  Component: (
                    await import('../features/dashboard/pages/DashboardPage')
                  ).DashboardPage,
                }),
              },
            ],
          },
          {
            element: <PermissionGuard permission="user.view" />,
            children: [
              {
                path: 'users',
                lazy: async () => ({
                  Component: (await import('../features/users/UsersPage'))
                    .UsersPage,
                }),
              },
            ],
          },
          {
            element: <PermissionGuard permission="operational-unit.view" />,
            children: [
              {
                path: 'operational-units',
                lazy: async () => ({
                  Component: (
                    await import('../features/operational-units/UnitsPage')
                  ).UnitsPage,
                }),
              },
              {
                path: 'operational-units/:id',
                lazy: async () => ({
                  Component: (
                    await import('../features/operational-units/UnitDetailPage')
                  ).UnitDetailPage,
                }),
              },
            ],
          },
          {
            element: <PermissionGuard permission="item.view" />,
            children: [
              {
                path: 'items',
                lazy: async () => ({
                  Component: (await import('../features/items/ItemsPage'))
                    .ItemsPage,
                }),
              },
            ],
          },
          {
            element: <PermissionGuard permission="rbac.view" />,
            children: [
              {
                path: 'rbac',
                lazy: async () => ({
                  Component: (await import('../features/rbac/RbacPage'))
                    .RbacPage,
                }),
              },
            ],
          },
          {
            element: <PermissionGuard permission="audit.view" />,
            children: [
              {
                path: 'activity-logs',
                lazy: async () => ({
                  Component: (
                    await import('../features/audit/ActivityLogsPage')
                  ).ActivityLogsPage,
                }),
              },
            ],
          },
          {
            element: <PermissionGuard permission="inventory.view" />,
            children: [
              {
                path: 'inventory',
                lazy: async () => ({
                  Component: (
                    await import('../features/inventory/InventoryPage')
                  ).InventoryPage,
                }),
              },
              {
                path: 'inventory/movements',
                lazy: async () => ({
                  Component: (
                    await import('../features/inventory/MovementsPage')
                  ).MovementsPage,
                }),
              },
            ],
          },
          {
            element: <PermissionGuard permission="transfer.view" />,
            children: [
              {
                path: 'stock-transfers',
                lazy: async () => ({
                  Component: (
                    await import('../features/transfers/TransfersPage')
                  ).TransfersPage,
                }),
              },
            ],
          },
          {
            element: (
              <PermissionGuard permission="transaction.create" transaction />
            ),
            children: [
              {
                path: 'pos',
                lazy: async () => ({
                  Component: (await import('../features/transactions/PosPage'))
                    .PosPage,
                }),
              },
              {
                path: 'ticketing',
                lazy: async () => ({
                  Component: (await import('../features/transactions/PosPage'))
                    .TicketingPage,
                }),
              },
            ],
          },
          {
            element: (
              <PermissionGuard permission="transaction.view" transaction />
            ),
            children: [
              {
                path: 'transactions',
                lazy: async () => ({
                  Component: (
                    await import('../features/transactions/TransactionsPage')
                  ).TransactionsPage,
                }),
              },
            ],
          },
          { path: '*', element: <NotFoundPage /> },
        ],
      },
    ],
  },
])
