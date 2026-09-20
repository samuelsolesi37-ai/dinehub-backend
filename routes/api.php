<?php

use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/restaurants/search', [RestaurantController::class, 'search']);
Route::get('/restaurants/top-rated', [RestaurantController::class, 'topRated']);
Route::get('/restaurants/{fsqId}', [RestaurantController::class, 'show']);

Route::get('/location/geocode', [LocationController::class, 'geocode']);
Route::get('/location/reverse', [LocationController::class, 'reverse']);

Route::get('/reviews/restaurant/{restaurantId}', [ReviewController::class, 'index']);

// Protected routes (require login)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{restaurantId}', [FavoriteController::class, 'destroy']);

    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
});