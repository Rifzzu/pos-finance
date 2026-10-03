<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Posting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostingsTest extends TestCase
{
    use RefreshDatabase;

    private array $fakeMe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->fakeMe = ['id' => 4, 'name' => 'F', 'email' => 'f@t.t', 'role' => 'staff_finance', 'outlet_id' => null, 'is_active' => true];
        Http::fake(function () {
            return Http::response(['message' => 'ok', 'data' => $this->fakeMe], 200);
        });
    }

    private function key(): array
    {
        return ['X-Service-Key' => 'test-key'];
    }

    private function salePayload(string $trx = 'TRX/BDG/20260615/0001', string $method = 'cash'): array
    {
        return [
            'idempotency_key' => "{$trx}:sale",
            'entry_type' => 'sale',
            'trx_number' => $trx,
            'outlet_id' => 1,
            'outlet_code' => 'BDG',
            'shift_id' => 7,
            'business_date' => '2026-06-15',
            'payment_method' => $method,
            'net_sales_amount' => '100000.00',
            'tax_amount' => '11000.00',
            'total_amount' => '111000.00',
            'occurred_at' => '2026-06-15T10:00:00+07:00',
        ];
    }

    private function linesByAccount(Posting $p): array
    {
        $out = [];
        foreach ($p->lines as $l) {
            $out[$l->account_code] = ['debit' => (string) $l->debit, 'credit' => (string) $l->credit];
        }

        return $out;
    }

    public function test_sale_cash_journal_t1(): void
    {
        $res = $this->postJson('/internal/postings', $this->salePayload(), $this->key())->assertStatus(201);
        $this->assertTrue($res->json('data.balanced'));

        $p = Posting::first();
        $lines = $this->linesByAccount($p);
        // T1: D 1101 111.000; K 4101 100.000; K 2101 11.000
        $this->assertSame(['debit' => '111000.00', 'credit' => '0.00'], $lines['1101']);
        $this->assertSame(['debit' => '0.00', 'credit' => '100000.00'], $lines['4101']);
        $this->assertSame(['debit' => '0.00', 'credit' => '11000.00'], $lines['2101']);
    }

    public function test_sale_debit_qris_accounts(): void
    {
        $this->postJson('/internal/postings', $this->salePayload('TRX/BDG/20260615/0002', 'debit'), $this->key())->assertStatus(201);
        $this->postJson('/internal/postings', $this->salePayload('TRX/BDG/20260615/0003', 'qris'), $this->key())->assertStatus(201);

        $this->assertSame('1102', Posting::where('trx_number', 'TRX/BDG/20260615/0002')->first()->lines()->where('debit', '>', 0)->first()->account_code);
        $this->assertSame('1103', Posting::where('trx_number', 'TRX/BDG/20260615/0003')->first()->lines()->where('debit', '>', 0)->first()->account_code);
    }

    public function test_idempotency_201_then_200(): void
    {
        $this->postJson('/internal/postings', $this->salePayload(), $this->key())->assertStatus(201);
        $this->postJson('/internal/postings', $this->salePayload(), $this->key())->assertStatus(200);

        $this->assertSame(1, Posting::count());
        $this->assertSame(3, \App\Models\JournalLine::count());
    }

    public function test_reversal_mirrors_sale(): void
    {
        $this->postJson('/internal/postings', $this->salePayload(), $this->key())->assertStatus(201);

        $rev = $this->salePayload();
        $rev['idempotency_key'] = 'TRX/BDG/20260615/0001:reversal';
        $rev['entry_type'] = 'reversal';
        $this->postJson('/internal/postings', $rev, $this->key())->assertStatus(201);

        $sale = Posting::where('entry_type', 'sale')->first();
        $reversal = Posting::where('entry_type', 'reversal')->first();
        $sl = $this->linesByAccount($sale);
        $rl = $this->linesByAccount($reversal);

        foreach ($sl as $code => $amounts) {
            $this->assertSame(['debit' => $amounts['credit'], 'credit' => $amounts['debit']], $rl[$code]);
        }
        $this->assertSame(3, $reversal->lines()->count());
    }

    public function test_reversal_without_sale_422_and_total_mismatch_422(): void
    {
        $rev = $this->salePayload('TRX/BDG/20260615/0009');
        $rev['idempotency_key'] = 'TRX/BDG/20260615/0009:reversal';
        $rev['entry_type'] = 'reversal';
        $this->postJson('/internal/postings', $rev, $this->key())
            ->assertStatus(422)->assertJsonPath('code', 'REVERSAL_WITHOUT_SALE');

        $bad = $this->salePayload('TRX/BDG/20260615/0010');
        $bad['total_amount'] = '99999.00';
        $this->postJson('/internal/postings', $bad, $this->key())
            ->assertStatus(422)->assertJsonPath('code', 'TOTAL_MISMATCH');
    }

    public function test_service_key_required_and_daily_sales(): void
    {
        $this->postJson('/internal/postings', $this->salePayload())->assertStatus(403);

        $this->postJson('/internal/postings', $this->salePayload(), $this->key())->assertStatus(201);
        $rev = $this->salePayload('TRX/BDG/20260615/0001');
        $rev['idempotency_key'] = 'TRX/BDG/20260615/0001:reversal';
        $rev['entry_type'] = 'reversal';
        $this->postJson('/internal/postings', $rev, $this->key())->assertStatus(201);
        // sale qris lain tetap bersih
        $this->postJson('/internal/postings', array_merge($this->salePayload('TRX/BDG/20260615/0002', 'qris'), [
            'idempotency_key' => 'TRX/BDG/20260615/0002:sale',
            'net_sales_amount' => '50000.00', 'tax_amount' => '5500.00', 'total_amount' => '55500.00',
        ]), $this->key())->assertStatus(201);

        $h = ['Authorization' => 'Bearer t'];
        $report = $this->getJson('/reports/daily-sales?outlet_id=1&business_date=2026-06-15', $h)
            ->assertOk()->json('data');

        $this->assertSame('0.00', $report['totals']['cash']);
        $this->assertSame('55500.00', $report['totals']['qris']);
        $this->assertSame(1, $report['trx_count']);
        $this->assertSame('50000.00', $report['net_sales']);
        $this->assertSame('5500.00', $report['tax_total']);

        // kasir tidak boleh lihat posting
        $this->fakeMe['role'] = 'kasir';
        $this->getJson('/postings', ['Authorization' => 'Bearer t'])->assertStatus(403);
    }

    public function test_zero_total_posting_is_balanced(): void
    {
        $payload = $this->salePayload('TRX/BDG/20260615/0077');
        $payload['net_sales_amount'] = '0.00';
        $payload['tax_amount'] = '0.00';
        $payload['total_amount'] = '0.00';
        $res = $this->postJson('/internal/postings', $payload, $this->key())->assertStatus(201);
        $this->assertTrue($res->json('data.balanced'));
    }
}
