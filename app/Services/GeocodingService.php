<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class GeocodingService
{
    protected string $baseUrl = 'https://nominatim.openstreetmap.org';

    /**
     * Convert a place name / address into lat/lng.
     */
    public function geocode(string $placeName): array
    {
        $response = Http::withHeaders([
                // Nominatim requires a real User-Agent identifying your app
                'User-Agent' => 'DineHub/1.0 (samuelsolesi377@gmail.com)',
            ])
            ->get("{$this->baseUrl}/search", [
                'q' => $placeName,
                'format' => 'json',
                'limit' => 1,
            ]);

        if ($response->failed()) {
            throw new Exception('Geocoding failed: ' . $response->status() . ' - ' . $response->body());
        }

        $results = $response->json();

        if (empty($results)) {
            throw new Exception("No location found for '{$placeName}'");
        }

        return [
            'lat' => (float) $results[0]['lat'],
            'lng' => (float) $results[0]['lon'],
            'display_name' => $results[0]['display_name'] ?? $placeName,
        ];
    }

    /**
     * Convert lat/lng back into a readable address (reverse geocoding).
     */
    public function reverseGeocode(float $lat, float $lng): array
    {
        $response = Http::withHeaders([
                'User-Agent' => 'DineHub/1.0 (samuelsolesi377@gmail.com)',
            ])
            ->get("{$this->baseUrl}/reverse", [
                'lat' => $lat,
                'lon' => $lng,
                'format' => 'json',
            ]);

        if ($response->failed()) {
            throw new Exception('Reverse geocoding failed: ' . $response->status() . ' - ' . $response->body());
        }

        return $response->json();
    }
}