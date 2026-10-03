<?php

namespace Tests\Feature;

use App\Models\Outlet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidnightBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_date_follows_wib_around_midnight(): void
    {
        Outlet::create(['code' => 'BDG', 'name' => 'Bandung', 'is_active' => true]);
        Http::fake(function () {
            return Http::response(['message' => 'ok', 'data' => [
                'id' => 11, 'name' => 'K', 'email' => 'k@t.t', 'role' => 'kasir', 'outlet_id' => 1, 'is_active' => true,
            ]], 200);
        });
        $h = ['Authorization' => 'Bearer t'];

        Carbon::setTestNow(Carbon::create(2026, 10, 1, 23, 59, 30, 'Asia/Jakarta'));
        $d1 = $this->postJson('/shifts/open', ['opening_cash' => 1000], $h)->assertStatus(201)->json('data.business_date');
        $this->assertSame('2026-10-01', $d1);

        Carbon::setTestNow(Carbon::create(2026, 10, 2, 0, 0, 30, 'Asia/Jakarta'));
        // shift masih open -> 409, tapi business_date request baru akan ikut tanggal baru;
        // tutup dulu lalu buka lagi untuk membuktikan tanggal berganti.
        $id = $this->getJson('/shifts/current', $h)->json('data.id');
        $this->postJson("/shifts/{$id}/close", ['actual_cash' => 1000], $h)->assertOk();
        $d2 = $this->postJson('/shifts/open', ['opening_cash' => 1000], $h)->assertStatus(201)->json('data.business_date');
        $this->assertSame('2026-10-02', $d2);

        Carbon::setTestNow();
    }
}
