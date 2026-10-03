import { Link, useLocation, useNavigate } from 'react-router';
import {
  LayoutDashboard,
  ShoppingCart,
  ReceiptText,
  Package,
  BookCopy,
  Scale,
  Users,
  LogOut,
} from 'lucide-react';
import { useAuthStore } from '@/store/auth';
import { MENU as MENU_DATA } from '@/lib/menu';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarInset,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarProvider,
  SidebarTrigger,
} from '@/components/ui/sidebar';
import { Separator } from '@/components/ui/separator';
import { Button } from '@/components/ui/button';

interface MenuItem {
  to: string;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  roles: import('@/types').Role[];
}

const ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
  '/': LayoutDashboard,
  '/kasir': ShoppingCart,
  '/transaksi': ReceiptText,
  '/produk': Package,
  '/posting': BookCopy,
  '/rekonsiliasi': Scale,
  '/users': Users,
};

const MENU: MenuItem[] = MENU_DATA.map((m) => ({
  ...m,
  icon: ICONS[m.to] ?? LayoutDashboard,
}));

export function AppLayout({ children }: { children: React.ReactNode }) {
  const { user, logout } = useAuthStore();
  const location = useLocation();
  const navigate = useNavigate();
  const items = MENU.filter((m) => user && m.roles.includes(user.role));

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  return (
    <SidebarProvider>
      <Sidebar>
        <SidebarHeader>
          <div className="px-2 py-1 text-sm font-bold">POS + Finance</div>
          <Separator />
        </SidebarHeader>
        <SidebarContent>
          <SidebarGroup>
            <SidebarGroupLabel>Menu</SidebarGroupLabel>
            <SidebarMenu>
              {items.map((m) => (
                <SidebarMenuItem key={m.to}>
                  <SidebarMenuButton asChild isActive={location.pathname === m.to} tooltip={m.label}>
                    <Link to={m.to}>
                      <m.icon className="h-4 w-4" />
                      <span>{m.label}</span>
                    </Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          </SidebarGroup>
        </SidebarContent>
        <SidebarFooter>
          <div className="px-2 text-xs text-muted-foreground">
            <div className="truncate font-medium text-foreground">{user?.name}</div>
            <div>{user?.role}</div>
          </div>
          <Button variant="ghost" size="sm" className="justify-start" onClick={handleLogout}>
            <LogOut className="h-4 w-4" /> Keluar
          </Button>
        </SidebarFooter>
      </Sidebar>
      <SidebarInset>
        <header className="flex h-12 items-center gap-2 border-b px-4">
          <SidebarTrigger />
          <span className="text-sm font-medium">POS + Finance System</span>
        </header>
        <div className="flex-1 p-4 md:p-6">{children}</div>
      </SidebarInset>
    </SidebarProvider>
  );
}
