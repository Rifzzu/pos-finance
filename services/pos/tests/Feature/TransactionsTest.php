<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TransactionsTest extends TestCase
{
    use RefreshDatabase;

    private array $fakeMe;

    protected function setUp(): void
    {
        parent::setUp();

        Outlet::create(['code' => 'BDG', 'name' => 'Bandung', 'is_active' => true]);
        Outlet::create(['code' => 'GRT', 'name' => 'Garut', 'is_active' => true]);
        Product::create(['sku' => 'P100', 'name' => 'Seratus Ribu', 'price' => '100000.00', 'is_active' => true]);
        Product::create(['sku' => 'P50', 'name' => 'Lima Puluh Ribu', 'price' => '50000.00', 'is_active' => true]);
        Product::create(['sku' => 'POFF', 'name' => 'Mati', 'price' => '10000.00', 'is_active' => false]);

        $this->fakeMe = $this->kasir(11, 1);
        Http::fake(function ($request) {
            // Finance down (500) agar alur failed teruji; integrasi sukses di FinanceIntegrationTest.
            if (str_contains($request->url(), 'internal/postings')) {
                return Http::response(['message' => 'err', 'code' => 'X', 'errors' => null], 500);
            }

            return Http::response(['message' => 'ok', 'data' => $this->fakeMe], 200);
        });
    }

    private function kasir(int $id, int $outlet): array
    {
        return ['id' => $id, 'name' => 'K', 'email' => 'k@t.t', 'role' => 'kasir', 'outlet_id' => $outlet, 'is_active' => true];
    }

    private function as(array $me)
    {
        $this->fakeMe = $me;

        return ['Authorization' => 'Bearer t'];
    }

    private function openShift(int $cashier = 11, int $outlet = 1): int
    {
        return Shift::create([
            'outlet_id' => $outlet,
            'cashier_id' => $cashier,
            'business_date' => now()->toDateString(),
            'opened_at' => now(),
            'opening_cash' => '200000.00',
            'status' => 'open',
            'active_guard' => "cashier-{$cashier}",
        ])->id;
    }

    private function pid(string $sku): int
    {
        return Product::where('sku', $sku)->first()->id;
    }

    public function test_create_calculation_t1(): void
    {
        $h = $this->as($this->kasir(11, 1));
        $this->openShift();

        $res = $this->postJson('/transactions', [
            'items' => [['product_id' => $this->pid('P100'), 'quantity' => 1]],
        ], $h)->assertStatus(201);

        $d = $res->json('data');
        $this->assertSame('100000.00', $d['subtotal']);
        $this->assertSame('0.00', $d['discount_amount']);
        $this->assertSame('11000.00', $d['tax_amount']);
        $this->assertSame('111000.00', $d['grand_total']);
        $this->assertSame('pending', $d['status']);
        $this->assertSame('none', $d['finance_sync_status']);
        $this->assertMatchesRegularExpression('#^TRX/BDG/\d{8}/0001$#', $d['trx_number']);
        $this->assertSame('P100', $d['items'][0]['sku']);
        $this->assertSame('100000.00', $d['items'][0]['unit_price']);
    }

    public function test_discount_and_rounding(): void
    {
        $h = $this->as($this->kasir(11, 1));
        $this->openShift();

        // diskon 10.000 dari 100.000 -> taxable 90.000 -> tax 9.900 -> total 99.900
        $res = $this->postJson('/transactions', [
            'items' => [['product_id' => $this->pid('P100'), 'quantity' => 1]],
            'discount_amount' => 10000,
        ], $h)->assertStatus(201);
        $this->assertSame('9900.00', $res->json('data.tax_amount'));
        $this->assertSame('99900.00', $res->json('data.grand_total'));

        // diskon > subtotal -> 422
        $this->postJson('/transactions', [
            'items' => [['product_id' => $this->pid('P100'), 'quantity' => 1]],
            'discount_amount' => 200000,
        ], $h)->assertStatus(422)->assertJsonPath('code', 'DISCOUNT_INVALID');

        // produk nonaktif -> 422
        $this->postJson('/transactions', [
            'items' => [['product_id' => $this->pid('POFF'), 'quantity' => 1]],
        ], $h)->assertStatus(422)->assertJsonPath('code', 'PRODUCT_INACTIVE');

        // tanpa shift open -> 409
        Shift::query()->delete();
        $this->postJson('/transactions', [
            'items' => [['product_id' => $this->pid('P100'), 'quantity' => 1]],
        ], $h)->assertStatus(409)->assertJsonPath('code', 'NO_OPEN_SHIFT');
    }

    public function test_sequence_no_duplicates(): void
    {
        $h = $this->as($this->kasir(11, 1));
        $this->openShift();

        $numbers = [];
        for ($i = 0; $i < 5; $i++) {
            $numbers[] = $this->postJson('/transactions', [
                'items' => [['product_id' => $this->pid('P50'), 'quantity' => 1]],
            ], $h)->assertStatus(201)->json('data.trx_number');
        }

        $this->assertCount(5, array_unique($numbers));
        $this->assertSame('TRX/BDG/'.now()->format('Ymd').'/0005', end($numbers));
    }

    public function test_pay_cash_and_double_pay(): void
    {
        $h = $this->as($this->kasir(11, 1));
        $this->openShift();
        $id = $this->postJson('/transactions', [
            'items' => [['product_id' => $this->pid('P100'), 'quantity' => 1]],
        ], $h)->json('data.id');

        // tunai kurang -> 422
        $this->postJson("/transactions/{$id}/pay", [
            'payment_method' => 'cash', 'paid_amount' => 100000,
        ], $h)->assertStatus(422)->assertJsonPath('code', 'INSUFFICIENT_CASH');

        // T1: tunai 150.000 -> kembalian 39.000
        $res = $this->postJson("/transactions/{$id}/pay", [
            'payment_method' => 'cash', 'paid_amount' => 150000,
        ], $h)->assertOk();
        $this->assertSame('paid', $res->json('data.status'));
        $this->assertSame('39000.00', $res->json('data.change_amount'));
        $this->assertSame('failed', $res->json('data.finance_sync_status'));

        // bayar lagi -> 409
        $this->postJson("/transactions/{$id}/pay", [
            'payment_method' => 'cash', 'paid_amount' => 150000,
        ], $h)->assertStatus(409)->assertJsonPath('code', 'ALREADY_PAID');
    }

    public function test_pay_noncash_exact(): void
    {
        $h = $this->as($this->kasir(11, 1));
        $this->openShift();
        $id = $this->postJson('/transactions', [
            'items' => [['product_id' => $this->pid('P50'), 'quantity' => 1]],
        ], $h)->json('data.id');

        // qris 55.500 pas -> ok, kembalian 0
        $this->postJson("/transactions/{$id}/pay", [
            'payment_method' => 'qris', 'paid_amount' => '55500.00',
        ], $h)->assertOk()->assertJsonPath('data.change_amount', '0.00');

        $id2 = $this->postJson('/transactions', [
            'items' => [['product_id' => $this->pid('P50'), 'quantity' => 1]],
        ], $h)->json('data.id');

        // debit tidak pas -> 422
        $this->postJson("/transactions/{$id2}/pay", [
            'payment_method' => 'debit', 'paid_amount' => '56000.00',
        ], $h)->assertStatus(422)->assertJsonPath('code', 'AMOUNT_MISMATCH');
    }

    public function test_void_rules_and_isolation(): void
    {
        $kasir = $this->as($this->kasir(11, 1));
        $this->openShift(11, 1);
        $id = $this->postJson('/transactions', [
            'items' => [['product_id' => $this->pid('P100'), 'quantity' => 1]],
        ], $kasir)->json('data.id');
        $this->postJson("/transactions/{$id}/pay", [
            'payment_method' => 'cash', 'paid_amount' => 111000,
        ], $kasir)->assertOk();

        // kasir tidak boleh void
        $this->postJson("/transactions/{$id}/void", ['reason' => 'x'], $kasir)->assertStatus(403);

        // supervisor outlet lain ditolak
        $this->as(['id' => 6, 'name' => 'S', 'email' => 's@t.t', 'role' => 'supervisor_pos', 'outlet_id' => 2, 'is_active' => true]);
        $this->postJson("/transactions/{$id}/void", ['reason' => 'salah'], ['Authorization' => 'Bearer t'])
            ->assertStatus(403);

        // void ok
        $this->as(['id' => 5, 'name' => 'S', 'email' => 's@t.t', 'role' => 'supervisor_pos', 'outlet_id' => 1, 'is_active' => true]);
        $this->postJson("/transactions/{$id}/void", ['reason' => 'salah input'], ['Authorization' => 'Bearer t'])
            ->assertOk()->assertJsonPath('data.status', 'void');

        // void lagi -> 409
        $this->postJson("/transactions/{$id}/void", ['reason' => 'lagi'], ['Authorization' => 'Bearer t'])
            ->assertStatus(409);

        // kasir lain tidak melihat transaksi ini
        $this->as($this->kasir(99, 1));
        $this->getJson("/transactions/{$id}", ['Authorization' => 'Bearer t'])->assertStatus(403);
        $this->getJson('/transactions', ['Authorization' => 'Bearer t'])
            ->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_void_closed_shift_409(): void
    {
        $h = $this->as($this->kasir(11, 1));
        $this->openShift(11, 1);
        $id = $this->postJson('/transactions', [
            'items' => [['product_id' => $this->pid('P100'), 'quantity' => 1]],
        ], $h)->json('data.id');
        $this->postJson("/transactions/{$id}/pay", [
            'payment_method' => 'cash', 'paid_amount' => 111000,
        ], $h)->assertOk();

        \App\Models\Shift::where('cashier_id', 11)->update(['status' => 'closed', 'active_guard' => null]);

        $this->as(['id' => 1, 'name' => 'S', 'email' => 's@t.t', 'role' => 'superadmin', 'outlet_id' => null, 'is_active' => true]);
        $this->postJson("/transactions/{$id}/void", ['reason' => 'x'], ['Authorization' => 'Bearer t'])
            ->assertStatus(409)->assertJsonPath('code', 'SHIFT_CLOSED');
    }

    public function test_receipt_pdf_download(): void
    {
        $h = $this->as($this->kasir(11, 1));
        $this->openShift(11, 1);
        $id = $this->postJson('/transactions', [
            'items' => [['product_id' => $this->pid('P100'), 'quantity' => 1]],
        ], $h)->json('data.id');

        $res = $this->get("/transactions/{$id}/receipt.pdf", $h);
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('Content-Type'));

        // kasir lain tidak boleh unduh
        $this->as($this->kasir(99, 1));
        $this->get("/transactions/{$id}/receipt.pdf", ['Authorization' => 'Bearer t'])->assertStatus(403);
    }
}
