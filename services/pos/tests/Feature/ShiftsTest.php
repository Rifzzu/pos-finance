<?php

namespace Tests\Feature;

use App\Models\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShiftsTest extends TestCase
{
    use RefreshDatabase;

    private array $fakeMe;

    protected function setUp(): void
    {
        parent::setUp();

        Outlet::create(['code' => 'BDG', 'name' => 'Bandung', 'is_active' => true]);
        Outlet::create(['code' => 'GRT', 'name' => 'Garut', 'is_active' => true]);

        $this->fakeMe = ['id' => 11, 'name' => 'Kasir', 'email' => 'k@t.t', 'role' => 'kasir', 'outlet_id' => 1, 'is_active' => true];
        Http::fake(function () {
            return Http::response(['message' => 'ok', 'data' => $this->fakeMe], 200);
        });
    }

    private function as(array $me)
    {
        $this->fakeMe = $me;

        return ['Authorization' => 'Bearer t'];
    }

    private function kasir(int $id = 11, int $outlet = 1): array
    {
        return ['id' => $id, 'name' => 'K', 'email' => 'k@t.t', 'role' => 'kasir', 'outlet_id' => $outlet, 'is_active' => true];
    }

    public function test_open_and_double_open_409(): void
    {
        $h = $this->as($this->kasir());

        $res = $this->postJson('/shifts/open', ['opening_cash' => 200000], $h)->assertStatus(201);
        $this->assertSame('open', $res->json('data.status'));
        $this->assertSame('BDG', $res->json('data.outlet_code'));

        $this->postJson('/shifts/open', ['opening_cash' => 100], $h)
            ->assertStatus(409)->assertJsonPath('code', 'SHIFT_ALREADY_OPEN');

        $this->getJson('/shifts/current', $h)->assertOk()->assertJsonPath('data.id', $res->json('data.id'));
    }

    public function test_current_404_when_none(): void
    {
        $this->getJson('/shifts/current', $this->as($this->kasir()))
            ->assertStatus(404)->assertJsonPath('code', 'SHIFT_NOT_FOUND');
    }

    public function test_superadmin_outlet_required(): void
    {
        $h = $this->as(['id' => 1, 'name' => 'S', 'email' => 's@t.t', 'role' => 'superadmin', 'outlet_id' => null, 'is_active' => true]);

        $this->postJson('/shifts/open', ['opening_cash' => 100], $h)->assertStatus(422);
        $this->postJson('/shifts/open', ['opening_cash' => 100, 'outlet_id' => 2], $h)
            ->assertStatus(201)->assertJsonPath('data.outlet_code', 'GRT');
    }

    public function test_summary_isolation(): void
    {
        $kasirA = $this->as($this->kasir(11, 1));
        $id = $this->postJson('/shifts/open', ['opening_cash' => 200000], $kasirA)->json('data.id');

        // kasir lain ditolak
        $this->getJson("/shifts/{$id}/summary", $this->as($this->kasir(22, 1)))
            ->assertStatus(403);

        // supervisor outlet sama boleh, outlet beda ditolak
        $this->getJson("/shifts/{$id}/summary", $this->as(
            ['id' => 5, 'name' => 'Sp', 'email' => 's@t.t', 'role' => 'supervisor_pos', 'outlet_id' => 1, 'is_active' => true]
        ))->assertOk()->assertJsonPath('data.summary.expected_cash', '200000.00');

        $this->getJson("/shifts/{$id}/summary", $this->as(
            ['id' => 6, 'name' => 'Sp', 'email' => 's@t.t', 'role' => 'supervisor_pos', 'outlet_id' => 2, 'is_active' => true]
        ))->assertStatus(403);

        // superadmin boleh
        $this->getJson("/shifts/{$id}/summary", $this->as(
            ['id' => 1, 'name' => 'S', 'email' => 's@t.t', 'role' => 'superadmin', 'outlet_id' => null, 'is_active' => true]
        ))->assertOk();
    }

    public function test_close_skeleton_and_reopen(): void
    {
        $h = $this->as($this->kasir(11, 1));
        $id = $this->postJson('/shifts/open', ['opening_cash' => 200000], $h)->json('data.id');

        $this->postJson("/shifts/{$id}/close", ['actual_cash' => 200000], $h)
            ->assertOk()
            ->assertJsonPath('data.shift.status', 'closed')
            ->assertJsonPath('data.shift.cash_variance', '0.00')
            ->assertJsonPath('data.unsynced_count', 0);

        // tutup lagi -> 409 (bukan open)
        $this->postJson("/shifts/{$id}/close", ['actual_cash' => 1], $h)->assertStatus(409);

        // bisa buka lagi setelah closed (guard dibersihkan)
        $this->postJson('/shifts/open', ['opening_cash' => 50000], $h)->assertStatus(201);

        // supervisor tidak boleh close
        $id2 = $this->getJson('/shifts/current', $h)->json('data.id');
        $this->postJson("/shifts/{$id2}/close", ['actual_cash' => 1], $this->as(
            ['id' => 5, 'name' => 'Sp', 'email' => 's@t.t', 'role' => 'supervisor_pos', 'outlet_id' => 1, 'is_active' => true]
        ))->assertStatus(403);
    }
}
