<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiError;
use App\Models\Outlet;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InternalController extends Controller
{
    public function eodSummary(Request $request)
    {
        $v = Validator::make($request->all(), [
            'outlet_id' => ['required', 'integer', 'min:1'],
            'business_date' => ['required', 'date_format:Y-m-d'],
        ]);

        if ($v->fails()) {
            throw ApiError::make(422, 'VALIDATION_ERROR', 'Validasi gagal.', $v->errors()->toArray());
        }

        $outlet = Outlet::find((int) $request->input('outlet_id'));

        if (! $outlet) {
            throw ApiError::make(404, 'OUTLET_NOT_FOUND', 'Outlet tidak dikenal.');
        }

        $date = $request->input('business_date');
        $shifts = DB::table('shifts')
            ->where('outlet_id', $outlet->id)
            ->whereDate('business_date', $date)
            ->get();

        $shiftCount = $shifts->count();
        $allClosed = $shiftCount > 0 && $shifts->every(fn ($s) => $s->status === 'closed');

        $totals = ['cash' => '0.00', 'debit' => '0.00', 'qris' => '0.00'];
        $trxCount = $voidCount = $unsynced = 0;
        $variance = '0.00';

        foreach ($shifts as $s) {
            $variance = Money::add($variance, (string) ($s->cash_variance ?? '0.00'));
        }

        $rows = DB::table('transactions')
            ->selectRaw('status, payment_method, finance_sync_status, COUNT(*) c, COALESCE(SUM(grand_total),0) t')
            ->where('outlet_id', $outlet->id)
            ->whereDate('business_date', $date)
            ->groupBy('status', 'payment_method', 'finance_sync_status')
            ->get();

        foreach ($rows as $r) {
            if ($r->status === 'paid' && isset($totals[$r->payment_method])) {
                $totals[$r->payment_method] = Money::add($totals[$r->payment_method], (string) $r->t);
                $trxCount += (int) $r->c;
            } elseif ($r->status === 'void') {
                $voidCount += (int) $r->c;
            }

            if (in_array($r->status, ['paid', 'void'], true) && $r->finance_sync_status !== 'synced') {
                $unsynced += (int) $r->c;
            }
        }

        return response()->json([
            'outlet_id' => $outlet->id,
            'business_date' => $date,
            'shift_count' => $shiftCount,
            'all_shifts_closed' => $allClosed,
            'trx_count' => $trxCount,
            'totals' => $totals,
            'void_count' => $voidCount,
            'cash_variance' => $variance,
            'unsynced_count' => $unsynced,
        ]);
    }
}
