import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { ReconBadge, SyncBadge, TrxBadge } from '@/components/common/StatusBadge';

describe('TrxBadge', () => {
  it('paid tampil hijau (success)', () => {
    const { container } = render(<TrxBadge status="paid" />);
    expect(screen.getByText('paid')).toBeInTheDocument();
    expect(container.firstChild).toHaveClass('bg-green-600');
  });
});

describe('SyncBadge + ReconBadge', () => {
  it('failed tampil merah, mismatch tampil merah', () => {
    const s = render(<SyncBadge status="failed" />);
    expect(screen.getByText('failed')).toBeInTheDocument();
    expect(s.container.firstChild).toHaveClass('bg-red-600');
    s.unmount();

    const r = render(<ReconBadge status="mismatch" />);
    expect(screen.getByText('mismatch')).toBeInTheDocument();
    expect(r.container.firstChild).toHaveClass('bg-red-600');
    r.unmount();
  });
});
