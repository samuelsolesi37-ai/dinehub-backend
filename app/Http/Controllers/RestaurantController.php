<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Services\GeoapifyService;
use App\Services\MapillaryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class RestaurantController extends Controller
{
    protected GeoapifyService $geoapify;
    protected MapillaryService $mapillary;

    public function __construct(
        GeoapifyService $geoapify,
        MapillaryService $mapillary
    ) {
        $this->geoapify = $geoapify;
        $this->mapillary = $mapillary;
    }

    /**
     * Search restaurants and food places.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'nullable|string|max:100',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'category' => 'nullable|string|in:restaurant,fast_food,cafe,bar,all',
        ]);

        try {
            $query = trim($request->input('query', ''));

            $results = $this->geoapify->search(
                $query,
                (float) $request->input('lat'),
                (float) $request->input('lng'),
                $request->input('category', 'all')
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
      } catch (Exception $e) {
    Log::error('DineHub restaurant details failed', [
        'fsq_id' => $fsqId,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);

    return response()->json([
        'success' => false,
        'message' => 'Unable to load restaurant details.',
        'error' => null,
    ], 502);
}
    }

    /**
     * Show a single restaurant.
     */
    public function show(string $fsqId): JsonResponse
    {
        $restaurant = Restaurant::where('fsq_id', $fsqId)->first();

        /*
        |--------------------------------------------------------------------------
        | If restaurant isn't already stored locally,
        | get its information from Geoapify.
        |--------------------------------------------------------------------------
        */

        if (!$restaurant) {
            try {
                $data = $this->geoapify->details($fsqId);

                if (!$data) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Restaurant not found.',
                    ], 404);
                }

                $restaurant = Restaurant::create([
                    'fsq_id' => $fsqId,
                    'name' => $data['name'] ?? 'Unknown Restaurant',
                    'address' => $data['location']['formatted_address']
                        ?? $data['address']
                        ?? null,
                    'lat' => $data['latitude'] ?? null,
                    'lng' => $data['longitude'] ?? null,
                    'category' => $data['categories'][0]['name']
                        ?? $data['categories'][0]
                        ?? null,
                    'raw_data' => $data,
                ]);
            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to load restaurant details.',
                    'error' => config('app.debug') ? $e->getMessage() : null,
                ], 502);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Mapillary street images
        |--------------------------------------------------------------------------
        */

        $nearbyImages = [];

        if ($restaurant->lat !== null && $restaurant->lng !== null) {
            $nearbyImages = $this->mapillary->near(
                (float) $restaurant->lat,
                (float) $restaurant->lng
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Ratings and reviews
        |--------------------------------------------------------------------------
        */

        $payload = $restaurant->toArray();

        $payload['rating'] = round(
            (float) ($restaurant->reviews()->avg('rating') ?? 0),
            1
        );

        $payload['review_count'] = $restaurant->reviews()->count();

        $payload['nearby_images'] = $nearbyImages;

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    /**
 * Return DineHub's highest-rated restaurants.
 *
 * Only restaurants that already have DineHub reviews
 * are included here.
 */
public function topRated(): JsonResponse
{
    $restaurants = Restaurant::withAvg('reviews', 'rating')
        ->withCount('reviews')
        ->whereHas('reviews')
        ->orderByDesc('reviews_avg_rating')
        ->limit(6)
        ->get();

    return response()->json([
        'success' => true,
        'data' => $restaurants,
    ]);
}
}
