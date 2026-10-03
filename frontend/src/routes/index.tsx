import { createBrowserRouter, Outlet } from 'react-router';
import { AppLayout } from '@/components/layout/AppLayout';
import { RequireAuth, RequireRole } from '@/routes/guards';
import { LoginPage } from '@/features/auth/LoginPage';
import { ForbiddenPage, NotFoundPage } from '@/features/auth/ErrorPages';
import { DashboardPage } from '@/features/dashboard/DashboardPage';
import { CashierPage } from '@/features/pos/CashierPage';
import { TransactionDetailPage, TransactionsPage } from '@/features/pos/TransactionsPage';
import { ProductsPage } from '@/features/pos/ProductsPage';
import { PostingsPage, PostingDetailPage } from '@/features/finance/PostingsPage';
import { ReconciliationPage } from '@/features/finance/ReconciliationPage';
import { ReconciliationDetailPage } from '@/features/finance/ReconciliationDetailPage';
import { UsersPage } from '@/features/users/UsersPage';

function Shell() {
  return (
    <AppLayout>
      <Outlet />
    </AppLayout>
  );
}

export const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  { path: '/forbidden', element: <ForbiddenPage /> },
  {
    element: (
      <RequireAuth>
        <Shell />
      </RequireAuth>
    ),
    children: [
      { path: '/', element: <DashboardPage /> },
      {
        path: '/kasir',
        element: (
          <RequireRole roles={['kasir', 'superadmin']}>
            <CashierPage />
          </RequireRole>
        ),
      },
      {
        path: '/transaksi',
        element: (
          <RequireRole roles={['kasir', 'supervisor_pos', 'superadmin']}>
            <TransactionsPage />
          </RequireRole>
        ),
      },
      {
        path: '/transaksi/:id',
        element: (
          <RequireRole roles={['kasir', 'supervisor_pos', 'superadmin']}>
            <TransactionDetailPage />
          </RequireRole>
        ),
      },
      {
        path: '/produk',
        element: (
          <RequireRole roles={['supervisor_pos', 'superadmin']}>
            <ProductsPage />
          </RequireRole>
        ),
      },
      {
        path: '/posting',
        element: (
          <RequireRole roles={['staff_finance', 'manager_finance', 'superadmin']}>
            <PostingsPage />
          </RequireRole>
        ),
      },
      {
        path: '/rekonsiliasi',
        element: (
          <RequireRole roles={['staff_finance', 'manager_finance', 'superadmin']}>
            <ReconciliationPage />
          </RequireRole>
        ),
      },
      {
        path: '/posting/:id',
        element: (
          <RequireRole roles={['staff_finance', 'manager_finance', 'superadmin']}>
            <PostingDetailPage />
          </RequireRole>
        ),
      },
      {
        path: '/rekonsiliasi/:id',
        element: (
          <RequireRole roles={['staff_finance', 'manager_finance', 'superadmin']}>
            <ReconciliationDetailPage />
          </RequireRole>
        ),
      },
      {
        path: '/users',
        element: (
          <RequireRole roles={['superadmin']}>
            <UsersPage />
          </RequireRole>
        ),
      },
    ],
  },
  { path: '*', element: <NotFoundPage /> },
]);
