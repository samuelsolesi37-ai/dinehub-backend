<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MapillaryService
{
    protected string $baseUrl = 'https://graph.mapillary.com/images';

    /**
     * Returns street-level image thumbnails captured near the given point.
     * This is crowdsourced street imagery, not verified restaurant photos —
     * it shows what the street looks like near this location, not necessarily the venue itself.
     */
    public function near(float $lat, float $lng, int $radiusMeters = 60, int $limit = 3): array
    {
        $token = config('services.mapillary.token');
        if (!$token) return [];

        $degOffset = $radiusMeters / 111000;
        $bbox = implode(',', [
            $lng - $degOffset, $lat - $degOffset,
            $lng + $degOffset, $lat + $degOffset,
        ]);

        $response = Http::timeout(10)->get($this->baseUrl, [
            'access_token' => $token,
            'fields' => 'id,thumb_1024_url',
            'bbox' => $bbox,
            'limit' => $limit,
        ]);

        if ($response->failed()) return [];

        return collect($response->json()['data'] ?? [])
            ->pluck('thumb_1024_url')
            ->filter()
            ->values()
            ->all();
    }
}