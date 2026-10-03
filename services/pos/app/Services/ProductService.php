<?php

namespace App\Services;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;

class ProductService
{
    public function paginate(?string $search, mixed $isActive, int $perPage = 15, int $page = 1)
    {
        $key = 'products:v1:'.md5(json_encode([$search, (string) $isActive, $perPage, $page]));

        try {
            return Cache::store('redis')->remember($key, 60, fn () => $this->queryPage($search, $isActive, $perPage, $page));
        } catch (\Throwable) {
            return $this->queryPage($search, $isActive, $perPage, $page);
        }
    }

    private function queryPage(?string $search, mixed $isActive, int $perPage, int $page)
    {
        $q = Product::query()->orderBy('name');

        if ($search) {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($isActive !== null && $isActive !== '') {
            $q->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $isActive);
        }

        return $q->paginate($perPage, ['*'], 'page', $page);
    }

    public function create(array $data): Product
    {
        $product = Product::create([
            'sku' => $data['sku'],
            'name' => $data['name'],
            'price' => Money::normalize($data['price']),
            'is_active' => true,
        ]);

        self::flushCache();

        return $product;
    }

    public function update(Product $product, array $data): Product
    {
        if (array_key_exists('price', $data)) {
            $data['price'] = Money::normalize($data['price']);
        }

        $product->update($data);
        self::flushCache();

        return $product->fresh();
    }

    public function setStatus(Product $product, bool $active): Product
    {
        $product->update(['is_active' => $active]);
        self::flushCache();

        return $product->fresh();
    }

    public static function flushCache(): void
    {
        try {
            Cache::store('redis')->flush();
        } catch (\Throwable) {
        }
    }
}
