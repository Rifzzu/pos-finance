import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import { PanelLeft } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useMobile } from '@/hooks/use-mobile';

const SIDEBAR_WIDTH = '16rem';
const SIDEBAR_WIDTH_ICON = '3rem';

type SidebarContextValue = {
  open: boolean;
  setOpen: (open: boolean) => void;
  openMobile: boolean;
  setOpenMobile: (open: boolean) => void;
  isMobile: boolean;
  toggleSidebar: () => void;
};

const SidebarContext = React.createContext<SidebarContextValue | null>(null);

function useSidebar() {
  const ctx = React.useContext(SidebarContext);
  if (!ctx) throw new Error('useSidebar harus dipakai di dalam SidebarProvider.');
  return ctx;
}

function SidebarProvider({ children, defaultOpen = true }: { children: React.ReactNode; defaultOpen?: boolean }) {
  const isMobile = useMobile();
  const [open, setOpen] = React.useState(defaultOpen);
  const [openMobile, setOpenMobile] = React.useState(false);
  const toggleSidebar = React.useCallback(() => {
    if (isMobile) setOpenMobile((v) => !v);
    else setOpen((v) => !v);
  }, [isMobile]);
  const value = React.useMemo(
    () => ({ open, setOpen, openMobile, setOpenMobile, isMobile, toggleSidebar }),
    [open, openMobile, isMobile, toggleSidebar],
  );
  return (
    <SidebarContext.Provider value={value}>
      <div className="flex min-h-svh w-full bg-background">{children}</div>
    </SidebarContext.Provider>
  );
}

function Sidebar({ className, children, ...props }: React.ComponentProps<'div'>) {
  const { isMobile, openMobile, setOpenMobile, open } = useSidebar();
  if (isMobile) {
    return (
      <Sheet open={openMobile} onOpenChange={setOpenMobile}>
        <SheetContent side="left" className="w-[16rem] p-0">
          <div className="flex h-full w-full flex-col bg-sidebar text-sidebar-foreground" {...props}>
            {children}
          </div>
        </SheetContent>
      </Sheet>
    );
  }
  return (
    <div
      className={cn('hidden h-svh shrink-0 bg-sidebar text-sidebar-foreground transition-[width] md:block', className)}
      style={{ width: open ? SIDEBAR_WIDTH : SIDEBAR_WIDTH_ICON }}
      {...props}
    >
      <div className="flex h-full w-full flex-col">{children}</div>
    </div>
  );
}

function SidebarTrigger({ className, ...props }: React.ComponentProps<typeof Button>) {
  const { toggleSidebar } = useSidebar();
  return (
    <Button variant="ghost" size="icon" className={cn('h-7 w-7', className)} onClick={toggleSidebar} {...props}>
      <PanelLeft />
      <span className="sr-only">Toggle sidebar</span>
    </Button>
  );
}

function SidebarHeader({ className, ...props }: React.ComponentProps<'div'>) {
  return <div className={cn('flex flex-col gap-2 p-2', className)} {...props} />;
}

function SidebarContent({ className, ...props }: React.ComponentProps<'div'>) {
  return <div className={cn('flex min-h-0 flex-1 flex-col gap-2 overflow-auto p-2', className)} {...props} />;
}

function SidebarFooter({ className, ...props }: React.ComponentProps<'div'>) {
  return <div className={cn('flex flex-col gap-2 p-2', className)} {...props} />;
}

function SidebarGroup({ className, ...props }: React.ComponentProps<'div'>) {
  return <div className={cn('flex w-full min-w-0 flex-col gap-1 p-0', className)} {...props} />;
}

function SidebarGroupLabel({ className, ...props }: React.ComponentProps<'div'>) {
  const { open } = useSidebar();
  if (!open) return null;
  return <div className={cn('px-2 text-xs font-medium text-sidebar-foreground/70', className)} {...props} />;
}

function SidebarMenu({ className, ...props }: React.ComponentProps<'ul'>) {
  return <ul className={cn('flex w-full min-w-0 flex-col gap-1', className)} {...props} />;
}

function SidebarMenuItem({ className, ...props }: React.ComponentProps<'li'>) {
  return <li className={cn('group/menu-item relative', className)} {...props} />;
}

const sidebarMenuButtonVariants = cva(
  'peer/menu-button flex w-full items-center gap-2 overflow-hidden rounded-md p-2 text-left text-sm outline-none transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 active:bg-sidebar-accent active:text-sidebar-accent-foreground disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50 data-[active=true]:bg-sidebar-accent data-[active=true]:font-medium data-[active=true]:text-sidebar-accent-foreground [&>svg]:size-4 [&>svg]:shrink-0',
  {
    variants: {
      variant: { default: '', outline: 'border border-sidebar-border bg-background shadow-sm' },
      size: { default: 'h-8 text-sm', sm: 'h-7 text-xs', lg: 'h-12 text-sm' },
    },
    defaultVariants: { variant: 'default', size: 'default' },
  },
);

function SidebarMenuButton({
  asChild = false,
  isActive = false,
  tooltip,
  className,
  ...props
}: React.ComponentProps<'button'> & { asChild?: boolean; isActive?: boolean; tooltip?: string } & VariantProps<typeof sidebarMenuButtonVariants>) {
  const { open, isMobile } = useSidebar();
  const Comp = asChild ? Slot : 'button';
  const button = <Comp data-active={isActive} className={cn(sidebarMenuButtonVariants(), className)} {...props} />;
  if (!open && !isMobile && tooltip) {
    return (
      <TooltipProvider>
        <Tooltip>
          <TooltipTrigger asChild>{button}</TooltipTrigger>
          <TooltipContent side="right">{tooltip}</TooltipContent>
        </Tooltip>
      </TooltipProvider>
    );
  }
  return button;
}

function SidebarInset({ className, ...props }: React.ComponentProps<'main'>) {
  return <main className={cn('relative flex min-h-svh min-w-0 flex-1 flex-col bg-background', className)} {...props} />;
}

export {
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
  useSidebar,
};
export { Separator };
