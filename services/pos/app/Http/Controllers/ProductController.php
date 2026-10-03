<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiError;
use App\Http\Requests\ProductStatusRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private ProductService $products) {}

    public function index(Request $request)
    {
        $data = $this->products->paginate(
            $request->input('search'),
            $request->input('is_active'),
            (int) $request->input('per_page', 15),
            (int) $request->input('page', 1)
        );

        return response()->json([
            'data' => ProductResource::collection($data->items()),
            'meta' => [
                'page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
            ],
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        return response()->json([
            'message' => 'Produk dibuat.',
            'data' => new ProductResource($this->products->create($request->validated())),
        ], 201);
    }

    public function show(string $id)
    {
        $product = Product::find($id);

        if (! $product) {
            throw ApiError::make(404, 'NOT_FOUND', 'Produk tidak ditemukan.');
        }

        return response()->json([
            'message' => 'Detail produk.',
            'data' => new ProductResource($product),
        ]);
    }

    public function update(UpdateProductRequest $request, string $id)
    {
        $product = Product::find($id);

        if (! $product) {
            throw ApiError::make(404, 'NOT_FOUND', 'Produk tidak ditemukan.');
        }

        return response()->json([
            'message' => 'Produk diperbarui.',
            'data' => new ProductResource($this->products->update($product, $request->validated())),
        ]);
    }

    public function setStatus(ProductStatusRequest $request, string $id)
    {
        $product = Product::find($id);

        if (! $product) {
            throw ApiError::make(404, 'NOT_FOUND', 'Produk tidak ditemukan.');
        }

        return response()->json([
            'message' => 'Status produk diperbarui.',
            'data' => new ProductResource(
                $this->products->setStatus($product, $request->boolean('is_active'))
            ),
        ]);
    }
}
