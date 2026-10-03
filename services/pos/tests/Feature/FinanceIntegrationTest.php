<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FinanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private array $fakeMe;

    /** 'ok' | 'dup' | 'fail' | 'down'. */
    private string $financeMode = 'ok';

    private array $financeSent = [];

    protected function setUp(): void
    {
        parent::setUp();

        Outlet::create(['code' => 'BDG', 'name' => 'Bandung', 'is_active' => true]);
        Product::create(['sku' => 'P100', 'name' => 'Seratus Ribu', 'price' => '100000.00', 'is_active' => true]);

        $this->fakeMe = ['id' => 11, 'name' => 'K', 'email' => 'k@t.t', 'role' => 'kasir', 'outlet_id' => 1, 'is_active' => true];
        $this->financeMode = 'ok';
        $this->financeSent = [];

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'internal/postings')) {
                $this->financeSent[] = $request->data();

                return match ($this->financeMode) {
                    'dup' => Http::response(['message' => 'duplikat', 'data' => null], 200),
                    'fail' => Http::response(['message' => 'rusak', 'code' => 'ERR', 'errors' => null], 500),
                    'down' => throw new ConnectionException('timeout'),
                    default => Http::response(['message' => 'tercatat', 'data' => ['id' => 1]], 201),
                };
            }

            return Http::response(['message' => 'ok', 'data' => $this->fakeMe], 200);
        });
    }

    private function as(array $me)
    {
        $this->fakeMe = $me;

        return ['Authorization' => 'Bearer t'];
    }

    private function kasir(): array
    {
        return ['id' => 11, 'name' => 'K', 'email' => 'k@t.t', 'role' => 'kasir', 'outlet_id' => 1, 'is_active' => true];
    }

    private function openShift(): int
    {
        return Shift::create([
            'outlet_id' => 1, 'cashier_id' => 11,
            'business_date' => now()->toDateString(), 'opened_at' => now(),
            'opening_cash' => '200000.00', 'status' => 'open', 'active_guard' => 'cashier-11',
        ])->id;
    }

    private function makePaid(array $h, string $method = 'cash', string $paid = '150000'): int
    {
        $pid = Product::where('sku', 'P100')->first()->id;
        $id = $this->postJson('/transactions', [
            'items' => [['product_id' => $pid, 'quantity' => 1]],
        ], $h)->json('data.id');
        $this->postJson("/transactions/{$id}/pay", [
            'payment_method' => $method, 'paid_amount' => $paid,
        ], $h)->assertOk();

        return $id;
    }

    public function test_sale_201_synced_with_correct_payload(): void
    {
        $h = $this->as($this->kasir());
        $this->openShift();
        $id = $this->makePaid($h);

        $trx = Transaction::find($id);
        $this->assertSame('synced', $trx->finance_sync_status);
        $this->assertNotNull($trx->finance_synced_at);
        $this->assertNull($trx->finance_last_error);

        $p = $this->financeSent[0];
        $this->assertSame("{$trx->trx_number}:sale", $p['idempotency_key']);
        $this->assertSame('sale', $p['entry_type']);
        $this->assertSame('100000.00', $p['net_sales_amount']);
        $this->assertSame('11000.00', $p['tax_amount']);
        $this->assertSame('111000.00', $p['total_amount']);
    }

    public function test_sale_200_synced_and_500_failed_but_paid(): void
    {
        $h = $this->as($this->kasir());
        $this->openShift();

        $this->financeMode = 'dup';
        $id = $this->makePaid($h);
        $this->assertSame('synced', Transaction::find($id)->finance_sync_status);

        $this->financeMode = 'fail';
        $pid = Product::where('sku', 'P100')->first()->id;
        $id2 = $this->postJson('/transactions', [
            'items' => [['product_id' => $pid, 'quantity' => 1]],
        ], $h)->json('data.id');
        $res = $this->postJson("/transactions/{$id2}/pay", [
            'payment_method' => 'cash', 'paid_amount' => '111000.00',
        ], $h)->assertOk();
        $this->assertSame('paid', $res->json('data.status'));
        $this->assertSame('failed', $res->json('data.finance_sync_status'));
        $this->assertNotEmpty($res->json('data.finance_last_error'));
    }

    public function test_timeout_failed_but_paid(): void
    {
        $h = $this->as($this->kasir());
        $this->openShift();
        $this->financeMode = 'down';
        $id = $this->makePaid($h);
        $this->assertSame('failed', Transaction::find($id)->finance_sync_status);
    }

    public function test_void_sends_sale_then_reversal_with_same_amounts(): void
    {
        $h = $this->as($this->kasir());
        $this->openShift();
        $id = $this->makePaid($h);
        $this->financeSent = [];

        $this->as(['id' => 5, 'name' => 'S', 'email' => 's@t.t', 'role' => 'supervisor_pos', 'outlet_id' => 1, 'is_active' => true]);
        $this->postJson("/transactions/{$id}/void", ['reason' => 'salah'], ['Authorization' => 'Bearer t'])
            ->assertOk()->assertJsonPath('data.finance_sync_status', 'synced');

        $types = array_column($this->financeSent, 'entry_type');
        $this->assertSame(['sale', 'reversal'], $types);
        $this->assertSame($this->financeSent[0]['total_amount'], $this->financeSent[1]['total_amount']);
        $this->assertSame($this->financeSent[0]['net_sales_amount'], $this->financeSent[1]['net_sales_amount']);
    }

    public function test_resync_rules_and_order(): void
    {
        $h = $this->as($this->kasir());
        $shiftId = $this->openShift();
        $this->financeMode = 'down';
        $id = $this->makePaid($h, 'qris', '111000.00');
        $this->assertSame('failed', Transaction::find($id)->finance_sync_status);

        // void saat sale belum synced: sale dulu lalu reversal
        $this->as(['id' => 5, 'name' => 'S', 'email' => 's@t.t', 'role' => 'supervisor_pos', 'outlet_id' => 1, 'is_active' => true]);
        $spv = ['Authorization' => 'Bearer t'];
        $this->financeSent = [];
        $this->postJson("/transactions/{$id}/void", ['reason' => 'salah'], $spv)->assertOk();
        $this->assertSame(['sale'], array_column($this->financeSent, 'entry_type'));
        $this->assertSame('failed', Transaction::find($id)->finance_sync_status);

        // resync void: sale lalu reversal
        $this->financeMode = 'ok';
        $this->financeSent = [];
        $this->as($this->kasir());
        $this->postJson("/transactions/{$id}/resync-finance", [], $h)->assertOk()
            ->assertJsonPath('data.finance_sync_status', 'synced');
        $this->assertSame(['sale', 'reversal'], array_column($this->financeSent, 'entry_type'));

        // sudah synced -> 409
        $this->postJson("/transactions/{$id}/resync-finance", [], $h)
            ->assertStatus(409)->assertJsonPath('code', 'ALREADY_SYNCED');

        // pending -> 409
        $pid = Product::where('sku', 'P100')->first()->id;
        $pending = $this->postJson('/transactions', [
            'items' => [['product_id' => $pid, 'quantity' => 1]],
        ], $h)->json('data.id');
        $this->postJson("/transactions/{$pending}/resync-finance", [], $h)
            ->assertStatus(409)->assertJsonPath('code', 'NOTHING_TO_RESYNC');

        // close: pending harus 409 dulu
        $closeRes = $this->postJson("/shifts/{$shiftId}/close", ['actual_cash' => 1], $h)
            ->assertStatus(409)->assertJsonPath('code', 'PENDING_TRANSACTIONS');
    }

    public function test_close_with_unsynced_and_variance(): void
    {
        $h = $this->as($this->kasir());
        $shiftId = $this->openShift();
        $this->financeMode = 'down';
        // T1 cash 111.000
        $this->makePaid($h);

        $res = $this->postJson("/shifts/{$shiftId}/close", ['actual_cash' => 311000], $h)->assertOk();
        $this->assertSame('closed', $res->json('data.shift.status'));
        $this->assertSame('311000.00', $res->json('data.shift.expected_cash'));
        $this->assertSame('0.00', $res->json('data.shift.cash_variance'));
        $this->assertSame(1, $res->json('data.unsynced_count'));
    }

    public function test_eod_summary(): void
    {
        $h = $this->as($this->kasir());
        $shiftId = $this->openShift();
        $this->financeMode = 'down';
        $this->makePaid($h); // cash 111.000, failed sync
        $date = now()->toDateString();

        $key = ['X-Service-Key' => 'test-key'];
        $res = $this->getJson("/internal/eod-summary?outlet_id=1&business_date={$date}", $key)->assertOk();
        $d = $res->json();
        $this->assertSame(1, $d['shift_count']);
        $this->assertFalse($d['all_shifts_closed']);
        $this->assertSame(1, $d['trx_count']);
        $this->assertSame('111000.00', $d['totals']['cash']);
        $this->assertSame(1, $d['unsynced_count']);

        // tanpa key -> 403
        $this->getJson("/internal/eod-summary?outlet_id=1&business_date={$date}")
            ->assertStatus(403)->assertJsonPath('code', 'SERVICE_KEY_INVALID');

        // outlet asing -> 404, tanggal buruk -> 422
        $this->getJson("/internal/eod-summary?outlet_id=99&business_date={$date}", $key)
            ->assertStatus(404);
        $this->getJson('/internal/eod-summary?outlet_id=1&business_date=xx', $key)
            ->assertStatus(422);
    }
}
