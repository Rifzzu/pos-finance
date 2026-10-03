import { useEffect } from 'react';
import { Navigate, useLocation } from 'react-router';
import { useAuthStore } from '@/store/auth';
import type { Role } from '@/types';
import { Card, CardContent } from '@/components/ui/card';

export function RequireAuth({ children }: { children: React.ReactElement }) {
  const { user, hydrated } = useAuthStore();
  const location = useLocation();
  useEffect(() => {
    if (hydrated) return;
    const t = setTimeout(() => useAuthStore.getState().setHydrated(), 1500);
    return () => clearTimeout(t);
  }, [hydrated]);
  if (!hydrated && !user) {
    return (
      <div className="flex min-h-svh items-center justify-center bg-background p-4 text-foreground">
        <Card className="w-full max-w-sm">
          <CardContent className="py-6 text-center text-sm text-muted-foreground">
            Memuat sesi...
          </CardContent>
        </Card>
      </div>
    );
  }
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  return children;
}

export function RequireRole({ roles, children }: { roles: Role[]; children: React.ReactElement }) {
  const { user } = useAuthStore();
  if (!user) return <Navigate to="/login" replace />;
  if (!roles.includes(user.role)) return <Navigate to="/forbidden" replace />;
  return children;
}
