export function formatRupiah(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return 'Rp0';
  const n = typeof value === 'string' ? Number(value) : value;
  if (Number.isNaN(n)) return 'Rp0';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n);
}

export function formatDateWib(value: string | null | undefined): string {
  if (!value) return '-';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '-';
  return new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(d);
}

export function formatDateOnly(value: string | null | undefined): string {
  if (!value) return '-';
  return value.slice(0, 10).split('-').reverse().join('/');
}
