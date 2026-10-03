<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
  table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  th, td { border: 1px solid #333; padding: 4px 6px; text-align: left; }
  .right { text-align: right; }
</style>
</head>
<body>
<h2>Struk Transaksi {{ $trx->trx_number }}</h2>
<p>Outlet: {{ $trx->outlet_code }} | Tanggal bisnis: {{ $trx->business_date }}<br>
Status: {{ $trx->status }} | Metode: {{ $trx->payment_method ?? '-' }}</p>
<table>
  <thead><tr><th>Produk</th><th>Qty</th><th class="right">Harga</th><th class="right">Subtotal</th></tr></thead>
  <tbody>
  @foreach ($trx->items as $item)
    <tr>
      <td>{{ $item->product_name }} ({{ $item->sku }})</td>
      <td>{{ $item->quantity }}</td>
      <td class="right">{{ $item->unit_price }}</td>
      <td class="right">{{ $item->subtotal }}</td>
    </tr>
  @endforeach
  </tbody>
</table>
<p>Subtotal: {{ $trx->subtotal }}<br>
Diskon: {{ $trx->discount_amount }}<br>
PPN: {{ $trx->tax_amount }}<br>
<strong>Grand Total: {{ $trx->grand_total }}</strong><br>
Dibayar: {{ $trx->paid_amount ?? '-' }} | Kembalian: {{ $trx->change_amount ?? '-' }}</p>
</body>
</html>
