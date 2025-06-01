<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\RajaOngkirService;

class ShippingController extends Controller
{
    public function searchDestination(Request $request, RajaOngkirService $rajaOngkir)
    {
        $request->validate([
            'search' => 'required|string',
        ]);

        return response()->json(
            $rajaOngkir->searchDestination(
                $request->input('search'),
                $request->input('limit', 5),
                $request->input('offset', 0)
            )
        );
    }

    public function cost(Request $request, RajaOngkirService $service)
    {
        $request->validate([
            'origin' => 'required|string',
            'destination' => 'required|string',
            'weight' => 'required|numeric',
            'courier' => 'required|string',
        ]);

        return response()->json(
            $service->calculateDomesticCost(
                $request->input('origin'),
                $request->input('destination'),
                $request->input('weight'),
                $request->input('courier'),
                $request->input('price', 'lowest')
            )
        );
    }

}
