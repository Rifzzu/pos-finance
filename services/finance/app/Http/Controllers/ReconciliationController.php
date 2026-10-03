<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiError;
use App\Http\Requests\ResolveReconciliationRequest;
use App\Http\Requests\RunReconciliationRequest;
use App\Http\Resources\ReconciliationResource;
use App\Models\EodReconciliation;
use App\Models\Posting;
use App\Services\ReconciliationService;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    public function __construct(private ReconciliationService $recon) {}

    public function run(RunReconciliationRequest $request)
    {
        $result = $this->recon->run(
            $request->attributes->get('authUser'),
            (int) $request->input('outlet_id'),
            (string) $request->input('business_date')
        );

        return response()->json([
            'message' => 'Rekonsiliasi selesai.',
            'data' => [
                'reconciliation' => new ReconciliationResource($result['reconciliation']),
                'unsynced_count' => $result['unsynced_count'],
            ],
        ]);
    }

    public function index(Request $request)
    {
        $q = EodReconciliation::query()->orderByDesc('business_date')->orderByDesc('id');

        if ($request->input('outlet_id')) {
            $q->where('outlet_id', $request->input('outlet_id'));
        }

        if ($request->input('status')) {
            $q->where('status', $request->input('status'));
        }

        if ($request->input('date_from')) {
            $q->whereDate('business_date', '>=', $request->input('date_from'));
        }

        if ($request->input('date_to')) {
            $q->whereDate('business_date', '<=', $request->input('date_to'));
        }

        $data = $q->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => ReconciliationResource::collection($data->items()),
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
        $row = EodReconciliation::find($id);

        if (! $row) {
            throw ApiError::make(404, 'NOT_FOUND', 'Rekonsiliasi tidak ditemukan.');
        }

        return response()->json([
            'message' => 'Detail rekonsiliasi.',
            'data' => new ReconciliationResource($row),
        ]);
    }

    public function resolve(ResolveReconciliationRequest $request, string $id)
    {
        $row = EodReconciliation::find($id);

        if (! $row) {
            throw ApiError::make(404, 'NOT_FOUND', 'Rekonsiliasi tidak ditemukan.');
        }

        return response()->json([
            'message' => 'Mismatch diselesaikan.',
            'data' => new ReconciliationResource(
                $this->recon->resolve(
                    $request->attributes->get('authUser'),
                    $row,
                    $request->string('resolution_note')->toString()
                )
            ),
        ]);
    }

    public function overview(Request $request)
    {
        $days = min(max((int) $request->input('days', 7), 1), 31);
        $today = now()->toDateString();

        $dates = collect(range($days - 1, 0))->map(
            fn ($d) => now()->subDays($d)->toDateString()
        )->values();

        $outletIds = Posting::distinct()->pluck('outlet_id')
            ->merge(EodReconciliation::distinct()->pluck('outlet_id'))
            ->unique()->values();

        $rows = EodReconciliation::whereIn('outlet_id', $outletIds->all())
            ->whereDate('business_date', '>=', $dates->first())
            ->get()
            ->keyBy(fn ($r) => $r->outlet_id.'|'.$r->business_date->format('Y-m-d'));

        $data = [];

        foreach ($outletIds as $outletId) {
            foreach ($dates as $date) {
                $row = $rows->get("{$outletId}|{$date}");
                $data[] = [
                    'outlet_id' => $outletId,
                    'business_date' => $date,
                    'status' => $row?->status ?? 'not_run',
                    'reconciliation_id' => $row?->id,
                ];
            }
        }

        return response()->json([
            'message' => 'Overview rekonsiliasi.',
            'data' => $data,
        ]);
    }
}
