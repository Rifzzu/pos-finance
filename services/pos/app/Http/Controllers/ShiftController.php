<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiError;
use App\Http\Requests\CloseShiftRequest;
use App\Http\Requests\OpenShiftRequest;
use App\Http\Resources\ShiftResource;
use App\Services\ShiftService;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct(private ShiftService $shifts) {}

    public function open(OpenShiftRequest $request)
    {
        $shift = $this->shifts->open(
            $request->attributes->get('authUser'),
            (string) $request->input('opening_cash'),
            $request->input('outlet_id')
        );

        return response()->json([
            'message' => 'Shift dibuka.',
            'data' => new ShiftResource($shift->load('outlet')),
        ], 201);
    }

    public function current(Request $request)
    {
        $shift = $this->shifts->current((int) $request->attributes->get('authUser')['id']);

        return response()->json([
            'message' => 'Shift berjalan.',
            'data' => new ShiftResource($shift->load('outlet')),
        ]);
    }

    public function summary(Request $request, string $id)
    {
        $authUser = $request->attributes->get('authUser');
        $shift = $this->shifts->findFor($authUser, (int) $id);

        return response()->json([
            'message' => 'Ringkasan shift.',
            'data' => [
                'shift' => new ShiftResource($shift->load('outlet')),
                'summary' => $this->shifts->summary($shift),
            ],
        ]);
    }

    public function close(CloseShiftRequest $request, string $id)
    {
        $authUser = $request->attributes->get('authUser');
        $shift = $this->shifts->findFor($authUser, (int) $id);

        if (($authUser['role'] ?? null) === 'supervisor_pos') {
            throw ApiError::make(403, 'FORBIDDEN', 'Akses ditolak untuk role ini.');
        }

        $result = $this->shifts->close($authUser, $shift, (string) $request->input('actual_cash'));

        return response()->json([
            'message' => 'Shift ditutup.',
            'data' => [
                'shift' => new ShiftResource($result['shift']->load('outlet')),
                'unsynced_count' => $result['unsynced_count'],
            ],
        ]);
    }
}
