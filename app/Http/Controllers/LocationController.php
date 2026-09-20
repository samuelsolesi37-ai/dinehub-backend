<?php

namespace App\Http\Controllers;

use App\Services\GeocodingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class LocationController extends Controller
{
    protected GeocodingService $geocoding;

    public function __construct(GeocodingService $geocoding)
    {
        $this->geocoding = $geocoding;
    }

    /**
     * GET /api/location/geocode?place=Lekki, Lagos
     */
    public function geocode(Request $request): JsonResponse
    {
        $request->validate([
            'place' => 'required|string|min:2',
        ]);

        try {
            $result = $this->geocoding->geocode($request->input('place'));

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * GET /api/location/reverse?lat=6.5244&lng=3.3792
     */
    public function reverse(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        try {
            $result = $this->geocoding->reverseGeocode(
                (float) $request->input('lat'),
                (float) $request->input('lng')
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }
    }
}