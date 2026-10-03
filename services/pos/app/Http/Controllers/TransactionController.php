<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiError;
use App\Http\Requests\PayTransactionRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\VoidTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Services\TransactionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(private TransactionService $trx) {}

    public function index(Request $request)
    {
        $data = $this->trx->paginate(
            $request->attributes->get('authUser'),
            $request->only(['status', 'outlet_id', 'shift_id', 'payment_method', 'finance_sync_status', 'date_from', 'date_to']),
            (int) $request->input('per_page', 15)
        );

        return response()->json([
            'data' => TransactionResource::collection($data->items()),
            'meta' => [
                'page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
            ],
        ]);
    }

    public function store(StoreTransactionRequest $request)
    {
        $trx = $this->trx->create(
            $request->attributes->get('authUser'),
            $request->input('items'),
            $request->input('discount_amount', '0')
        );

        return response()->json([
            'message' => 'Transaksi dibuat.',
            'data' => new TransactionResource($trx),
        ], 201);
    }

    public function show(Request $request, string $id)
    {
        return response()->json([
            'message' => 'Detail transaksi.',
            'data' => new TransactionResource(
                $this->trx->findFor($request->attributes->get('authUser'), (int) $id)
            ),
        ]);
    }

    public function receipt(Request $request, string $id)
    {
        $trx = $this->trx->findFor($request->attributes->get('authUser'), (int) $id);

        $pdf = Pdf::loadView('receipt', ['trx' => $trx->load('items')]);
        $safe = str_replace(['/', '\\'], '-', (string) $trx->trx_number);

        return $pdf->download("struk-{$safe}.pdf");
    }

    public function pay(PayTransactionRequest $request, string $id)
    {
        $authUser = $request->attributes->get('authUser');
        $trx = $this->trx->findFor($authUser, (int) $id);

        $paid = $this->trx->pay(
            $authUser,
            $trx,
            $request->string('payment_method')->toString(),
            $request->input('paid_amount')
        );

        return response()->json([
            'message' => 'Transaksi dibayar.',
            'data' => new TransactionResource($paid),
        ]);
    }

    public function void(VoidTransactionRequest $request, string $id)
    {
        $authUser = $request->attributes->get('authUser');
        $trx = $this->trx->findFor($authUser, (int) $id);

        $voided = $this->trx->void($authUser, $trx, $request->string('reason')->toString());

        return response()->json([
            'message' => 'Transaksi di-void.',
            'data' => new TransactionResource($voided),
        ]);
    }

    public function resync(Request $request, string $id)
    {
        $authUser = $request->attributes->get('authUser');
        $trx = $this->trx->findFor($authUser, (int) $id);

        if (($authUser['role'] ?? null) === 'kasir' && (int) $trx->cashier_id !== (int) $authUser['id']) {
            throw new ApiError(403, 'FORBIDDEN', 'Akses ditolak untuk transaksi ini.');
        }

        return response()->json([
            'message' => 'Resync ke Finance selesai.',
            'data' => new TransactionResource($this->trx->resync($trx)),
        ]);
    }
}
