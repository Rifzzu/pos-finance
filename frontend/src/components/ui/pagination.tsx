import * as React from 'react';
import { ChevronLeftIcon, ChevronRightIcon, MoreHorizontalIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button, buttonVariants } from '@/components/ui/button';

const Pagination = ({ className, ...props }: React.ComponentProps<'nav'>) => (
  <nav role="navigation" aria-label="pagination" className={cn('mx-auto flex w-full justify-center', className)} {...props} />
);

const PaginationContent = React.forwardRef<HTMLUListElement, React.ComponentProps<'ul'>>(({ className, ...props }, ref) => (
  <ul ref={ref} className={cn('flex flex-row items-center gap-1', className)} {...props} />
));

const PaginationItem = React.forwardRef<HTMLLIElement, React.ComponentProps<'li'>>(({ ...props }, ref) => (
  <li ref={ref} {...props} />
));

type PaginationLinkProps = { isActive?: boolean } & React.ComponentProps<typeof Button>;

const PaginationLink = ({ className, isActive, size = 'icon', ...props }: PaginationLinkProps) => (
  <Button
    aria-current={isActive ? 'page' : undefined}
    variant={isActive ? 'outline' : 'ghost'}
    size={size}
    className={className}
    {...props}
  />
);

const PaginationPrevious = ({ className, ...props }: React.ComponentProps<typeof PaginationLink>) => (
  <PaginationLink aria-label="Halaman sebelumnya" size="default" className={cn('gap-1 px-2.5', className)} {...props}>
    <ChevronLeftIcon className="h-4 w-4" />
    <span className="hidden sm:block">Sebelumnya</span>
  </PaginationLink>
);

const PaginationNext = ({ className, ...props }: React.ComponentProps<typeof PaginationLink>) => (
  <PaginationLink aria-label="Halaman berikutnya" size="default" className={cn('gap-1 px-2.5', className)} {...props}>
    <span className="hidden sm:block">Berikutnya</span>
    <ChevronRightIcon className="h-4 w-4" />
  </PaginationLink>
);

const PaginationEllipsis = ({ className, ...props }: React.ComponentProps<'span'>) => (
  <span aria-hidden className={cn('flex h-9 w-9 items-center justify-center', className)} {...props}>
    <MoreHorizontalIcon className="h-4 w-4" />
  </span>
);

export { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious, PaginationEllipsis, buttonVariants };
