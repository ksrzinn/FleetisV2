<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Commercial\Models\PerKmFreightRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FreightRatesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $clientId = $request->query('client_id');
        $pricingModel = $request->query('pricing_model');

        if ($pricingModel === 'fixed') {
            $tables = ClientFreightTable::with('fixedRates.prices')
                ->where('client_id', $clientId)
                ->where('pricing_model', 'fixed')
                ->where('active', true)
                ->get(['id', 'name', 'client_id']);

            return response()->json(['tables' => $tables]);
        }

        if ($pricingModel === 'per_km') {
            $rates = PerKmFreightRate::with('prices')
                ->where('client_id', $clientId)
                ->get(['id', 'client_id', 'state']);

            return response()->json(['rates' => $rates]);
        }

        return response()->json(['tables' => [], 'rates' => []]);
    }
}
