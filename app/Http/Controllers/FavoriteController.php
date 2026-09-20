<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $favorites = $request->user()
            ->favorites()
            ->with('restaurant')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $favorites,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
        ]);

        $favorite = Favorite::firstOrCreate([
            'user_id' => $request->user()->id,
            'restaurant_id' => $validated['restaurant_id'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $favorite,
        ], 201);
    }

    public function destroy(Request $request, int $restaurantId): JsonResponse
    {
        $deleted = Favorite::where('user_id', $request->user()->id)
            ->where('restaurant_id', $restaurantId)
            ->delete();

        return response()->json([
            'success' => true,
            'deleted' => (bool) $deleted,
        ]);
    }
}