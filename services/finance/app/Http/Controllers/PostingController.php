<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiError;
use App\Http\Resources\PostingResource;
use App\Models\Posting;
use App\Services\PostingService;
use Illuminate\Http\Request;

class PostingController extends Controller
{
    public function __construct(private PostingService $postings) {}

    public function index(Request $request)
    {
        $q = Posting::query()->with('lines')->orderByDesc('id');

        if (! empty($request->input('outlet_id'))) {
            $q->where('outlet_id', $request->input('outlet_id'));
        }

        if (! empty($request->input('business_date'))) {
            $q->whereDate('business_date', $request->input('business_date'));
        }

        if (! empty($request->input('date_from'))) {
            $q->whereDate('business_date', '>=', $request->input('date_from'));
        }

        if (! empty($request->input('date_to'))) {
            $q->whereDate('business_date', '<=', $request->input('date_to'));
        }

        foreach (['payment_method', 'entry_type'] as $f) {
            if (! empty($request->input($f))) {
                $q->where($f, $request->input($f));
            }
        }

        $data = $q->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => PostingResource::collection($data->items()),
            'meta' => [
                'page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
            ],
        ]);
    }

    public function show(string $id)
    {
        $posting = Posting::with('lines')->find($id);

        if (! $posting) {
            throw ApiError::make(404, 'NOT_FOUND', 'Posting tidak ditemukan.');
        }

        return response()->json([
            'message' => 'Detail posting.',
            'data' => new PostingResource($posting),
        ]);
    }

    public function dailySales(Request $request)
    {
        if (! $request->input('outlet_id') || ! $request->input('business_date')) {
            throw ApiError::make(422, 'VALIDATION_ERROR', 'outlet_id dan business_date wajib.', [
                'outlet_id' => ['Wajib diisi.'],
                'business_date' => ['Wajib diisi (Y-m-d).'],
            ]);
        }

        return response()->json([
            'message' => 'Penjualan harian ledger.',
            'data' => $this->postings->dailySales(
                (int) $request->input('outlet_id'),
                (string) $request->input('business_date')
            ),
        ]);
    }
}
