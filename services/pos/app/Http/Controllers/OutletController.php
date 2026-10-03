<?php

namespace App\Http\Controllers;

use App\Http\Resources\OutletResource;
use App\Models\Outlet;

class OutletController extends Controller
{
    public function index()
    {
        $outlets = Outlet::where('is_active', true)->orderBy('code')->get();

        return response()->json([
            'message' => 'Daftar outlet.',
            'data' => OutletResource::collection($outlets),
        ]);
    }
}
