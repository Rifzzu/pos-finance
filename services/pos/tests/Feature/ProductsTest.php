<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductsTest extends TestCase
{
    use RefreshDatabase;

    private array $fakeMe;

    private bool $fakeDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        Outlet::create(['code' => 'BDG', 'name' => 'Bandung', 'is_active' => true]);
        Product::create(['sku' => 'A-1', 'name' => 'Kopi', 'price' => '50000.00', 'is_active' => true]);
        Product::create(['sku' => 'A-2', 'name' => 'Teh', 'price' => '25000.00', 'is_active' => false]);

        $this->fakeMe = ['id' => 1, 'name' => 'T', 'email' => 't@t.t', 'role' => 'kasir', 'outlet_id' => 1, 'is_active' => true];
        $this->fakeDown = false;
        Http::fake(function () {
            if ($this->fakeDown) {
                throw new \Illuminate\Http\Client\ConnectionException('down');
            }

            return Http::response(['message' => 'ok', 'data' => $this->fakeMe], 200);
        });
    }

    private function asRole(string $role)
    {
        $this->fakeMe['role'] = $role;

        return ['Authorization' => 'Bearer test-token'];
    }

    public function test_outlets_only_active(): void
    {
        Outlet::create(['code' => 'X', 'name' => 'Nonaktif', 'is_active' => false]);

        $this->getJson('/outlets', ['Authorization' => 'Bearer t'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BDG');
    }

    public function test_products_search_filter_pagination(): void
    {
        $res = $this->getJson('/products?search=kopi', ['Authorization' => 'Bearer t'])->assertOk();
        $this->assertCount(1, $res->json('data'));

        $res = $this->getJson('/products?is_active=0', ['Authorization' => 'Bearer t'])->assertOk();
        $this->assertCount(1, $res->json('data'));

        $res = $this->getJson('/products?per_page=1', ['Authorization' => 'Bearer t'])->assertOk();
        $this->assertEquals(2, $res->json('meta.total'));
        $this->assertEquals(2, $res->json('meta.last_page'));
    }

    public function test_kasir_read_only(): void
    {
        $this->asRole('kasir');
        $this->getJson('/products', ['Authorization' => 'Bearer t'])->assertOk();
        $this->postJson('/products', ['sku' => 'B', 'name' => 'B', 'price' => 1000], ['Authorization' => 'Bearer t'])
            ->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_supervisor_crud_and_status(): void
    {
        $h = $this->asRole('supervisor_pos');

        $created = $this->postJson('/products', ['sku' => 'B-1', 'name' => 'Gula', 'price' => 18000], $h)
            ->assertStatus(201);
        $this->assertSame('18000.00', $created->json('data.price'));

        $id = $created->json('data.id');
        $this->patchJson("/products/{$id}", ['price' => '20000.50'], $h)
            ->assertOk()->assertJsonPath('data.price', '20000.50');

        $this->patchJson("/products/{$id}/status", ['is_active' => false], $h)
            ->assertOk()->assertJsonPath('data.is_active', false);
    }

    public function test_no_token_401_and_auth_down_503(): void
    {
        $this->getJson('/products')->assertStatus(401);

        $this->fakeDown = true;
        $this->getJson('/products', ['Authorization' => 'Bearer t'])
            ->assertStatus(503)->assertJsonPath('code', 'AUTH_UNAVAILABLE');
    }
}
