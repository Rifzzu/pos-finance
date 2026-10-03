<?php

namespace Tests\Feature;

use App\Models\EodReconciliation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private array $fakeMe;

    private array $posSummary;

    private bool $posDown = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->fakeMe = ['id' => 4, 'name' => 'F', 'email' => 'f@t.t', 'role' => 'staff_finance', 'outlet_id' => null, 'is_active' => true];
        $this->posDown = false;
        $this->posSummary = [
            'outlet_id' => 1, 'business_date' => '2026-06-15',
            'shift_count' => 1, 'all_shifts_closed' => true,
            'trx_count' => 2,
            'totals' => ['cash' => '111000.00', 'debit' => '0.00', 'qris' => '55500.00'],
            'void_count' => 1, 'cash_variance' => '0.00', 'unsynced_count' => 0,
        ];

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'eod-summary')) {
                if ($this->posDown) {
                    throw new ConnectionException('down');
                }

                return Http::response($this->posSummary, 200);
            }

            return Http::response(['message' => 'ok', 'data' => $this->fakeMe], 200);
        });
    }

    private function h(string $role = 'staff_finance'): array
    {
        $this->fakeMe['role'] = $role;

        return ['Authorization' => 'Bearer t'];
    }

    private function postSale(string $trx, string $method, string $net, string $tax, string $total): void
    {
        $this->postJson('/internal/postings', [
            'idempotency_key' => "{$trx}:sale", 'entry_type' => 'sale', 'trx_number' => $trx,
            'outlet_id' => 1, 'outlet_code' => 'BDG', 'shift_id' => 7,
            'business_date' => '2026-06-15', 'payment_method' => $method,
            'net_sales_amount' => $net, 'tax_amount' => $tax, 'total_amount' => $total,
            'occurred_at' => '2026-06-15T10:00:00+07:00',
        ], ['X-Service-Key' => 'test-key'])->assertStatus(201);
    }

    private function runRecon(?array $h = null): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/reconciliations/run', [
            'outlet_id' => 1, 'business_date' => '2026-06-15',
        ], $h ?? $this->h());
    }

    public function test_matched(): void
    {
        // S1: cash 111.000 + qris 55.500, 2 trx bersih
        $this->postSale('TRX/BDG/20260615/0001', 'cash', '100000.00', '11000.00', '111000.00');
        $this->postSale('TRX/BDG/20260615/0002', 'qris', '50000.00', '5500.00', '55500.00');

        $res = $this->runRecon()->assertOk();
        $this->assertSame('matched', $res->json('data.reconciliation.status'));
        $this->assertNull($res->json('data.reconciliation.mismatch_reasons'));

        // run ulang setelah matched -> 409
        $this->runRecon()->assertStatus(409);
    }

    public function test_mismatch_amount_and_count_then_rerun_matched_same_row(): void
    {
        // ledger hanya cash; qris belum masuk
        $this->postSale('TRX/BDG/20260615/0001', 'cash', '100000.00', '11000.00', '111000.00');

        $res = $this->runRecon()->assertOk();
        $this->assertSame('mismatch', $res->json('data.reconciliation.status'));
        $id = $res->json('data.reconciliation.id');

        $types = array_column($res->json('data.reconciliation.mismatch_reasons'), 'type');
        $this->assertContains('AMOUNT_DIFF', $types);
        $this->assertContains('COUNT_DIFF', $types);

        // kirim qris yang kurang lalu jalankan ulang -> matched, baris sama
        $this->postSale('TRX/BDG/20260615/0002', 'qris', '50000.00', '5500.00', '55500.00');
        $res2 = $this->runRecon()->assertOk();
        $this->assertSame('matched', $res2->json('data.reconciliation.status'));
        $this->assertSame($id, $res2->json('data.reconciliation.id'));
        $this->assertSame(1, EodReconciliation::count());
    }

    public function test_mismatch_cash_variance_and_resolve(): void
    {
        $this->postSale('TRX/BDG/20260615/0001', 'cash', '100000.00', '11000.00', '111000.00');
        $this->postSale('TRX/BDG/20260615/0002', 'qris', '50000.00', '5500.00', '55500.00');
        $this->posSummary['cash_variance'] = '-5000.00';

        $res = $this->runRecon()->assertOk();
        $this->assertSame('mismatch', $res->json('data.reconciliation.status'));
        $id = $res->json('data.reconciliation.id');
        $types = array_column($res->json('data.reconciliation.mismatch_reasons'), 'type');
        $this->assertContains('CASH_VARIANCE', $types);

        // staff tidak boleh resolve
        $this->patchJson("/reconciliations/{$id}/resolve", ['resolution_note' => 'x'], $this->h('staff_finance'))
            ->assertStatus(403);

        // tanpa catatan ditolak
        $mgr = $this->h('manager_finance');
        $this->patchJson("/reconciliations/{$id}/resolve", [], $mgr)->assertStatus(422);

        // resolve ok
        $this->patchJson("/reconciliations/{$id}/resolve", ['resolution_note' => 'selisih kas dicatat'], $mgr)
            ->assertOk()->assertJsonPath('data.status', 'resolved');

        // run ulang setelah resolved -> 409
        $this->runRecon($mgr)->assertStatus(409);
    }

    public function test_guards_open_shifts_no_shifts_pos_down(): void
    {
        $this->postSale('TRX/BDG/20260615/0001', 'cash', '100000.00', '11000.00', '111000.00');

        $this->posSummary['all_shifts_closed'] = false;
        $this->runRecon()->assertStatus(409);

        $this->posSummary['all_shifts_closed'] = true;
        $this->posSummary['shift_count'] = 0;
        $this->runRecon()->assertStatus(422);

        $this->posSummary['shift_count'] = 1;
        $this->posDown = true;
        $this->runRecon()->assertStatus(503)->assertJsonPath('code', 'POS_UNAVAILABLE');
    }
}
