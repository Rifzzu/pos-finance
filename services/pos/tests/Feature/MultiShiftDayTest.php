<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MultiShiftDayTest extends TestCase
{
    use RefreshDatabase;

    private array $fakeMe;

    protected function setUp(): void
    {
        parent::setUp();

        Outlet::create(['code' => 'BDG', 'name' => 'Bandung', 'is_active' => true]);
        Product::create(['sku' => 'P100', 'name' => 'Seratus', 'price' => '100000.00', 'is_active' => true]);

        $this->fakeMe = ['id' => 11, 'name' => 'K', 'email' => 'k@t.t', 'role' => 'kasir', 'outlet_id' => 1, 'is_active' => true];
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'internal/postings')) {
                return Http::response(['message' => 'ok'], 500);
            }

            return Http::response(['message' => 'ok', 'data' => $this->fakeMe], 200);
        });
    }

    private function h(): array
    {
        return ['Authorization' => 'Bearer t'];
    }

    public function test_two_shifts_same_day_aggregate_in_eod(): void
    {
        $h = $this->h();
        $date = now()->toDateString();

        // shift 1: cash 111.000, variance +1000
        $s1 = $this->postJson('/shifts/open', ['opening_cash' => 200000], $h)->json('data.id');
        $pid = Product::first()->id;
        $t1 = $this->postJson('/transactions', ['items' => [['product_id' => $pid, 'quantity' => 1]]], $h)->json('data.id');
        $this->postJson("/transactions/{$t1}/pay", ['payment_method' => 'cash', 'paid_amount' => 111000], $h)->assertOk();
        $this->postJson("/shifts/{$s1}/close", ['actual_cash' => 312000], $h)->assertOk();

        // shift 2: qris 55.500 via produk 50rb
        Product::create(['sku' => 'P50', 'name' => 'Lima', 'price' => '50000.00', 'is_active' => true]);
        $s2 = $this->postJson('/shifts/open', ['opening_cash' => 50000], $h)->json('data.id');
        $p50 = Product::where('sku', 'P50')->first()->id;
        $t2 = $this->postJson('/transactions', ['items' => [['product_id' => $p50, 'quantity' => 1]]], $h)->json('data.id');
        $this->postJson("/transactions/{$t2}/pay", ['payment_method' => 'qris', 'paid_amount' => 55500], $h)->assertOk();
        $this->postJson("/shifts/{$s2}/close", ['actual_cash' => 50000], $h)->assertOk();

        $res = $this->getJson("/internal/eod-summary?outlet_id=1&business_date={$date}", ['X-Service-Key' => 'test-key'])
            ->assertOk()->json();

        $this->assertSame(2, $res['shift_count']);
        $this->assertTrue($res['all_shifts_closed']);
        $this->assertSame(2, $res['trx_count']);
        $this->assertSame('111000.00', $res['totals']['cash']);
        $this->assertSame('55500.00', $res['totals']['qris']);
        $this->assertSame('1000.00', $res['cash_variance']);
        $this->assertSame(2, $res['unsynced_count']);
    }

    public function test_full_discount_transaction_can_be_created_and_paid(): void
    {
        $h = $this->h();
        $this->postJson('/shifts/open', ['opening_cash' => 0], $h)->assertStatus(201);

        $pid = Product::first()->id;
        $res = $this->postJson('/transactions', [
            'items' => [['product_id' => $pid, 'quantity' => 1]],
            'discount_amount' => 100000,
        ], $h)->assertStatus(201);

        $this->assertSame('0.00', $res->json('data.tax_amount'));
        $this->assertSame('0.00', $res->json('data.grand_total'));

        $id = $res->json('data.id');
        $this->postJson("/transactions/{$id}/pay", ['payment_method' => 'cash', 'paid_amount' => 0], $h)
            ->assertOk()->assertJsonPath('data.change_amount', '0.00');
        $this->assertSame('paid', Transaction::find($id)->status);
    }
}
