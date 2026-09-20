<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeoapifyService
{
    protected string $baseUrl = 'https://api.geoapify.com/v2/places';

    /**
     * Search for restaurants and food places using Geoapify.
     */
    public function search(
        string $query,
        float $lat,
        float $lng,
        string $category = 'all',
        int $radius = 25000
    ): array {
        $apiKey = config('services.geoapify.key');

        if (!$apiKey) {
            throw new RuntimeException(
                'GEOAPIFY_API_KEY is not configured.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Category mapping
        |--------------------------------------------------------------------------
        */

        $categories = match ($category) {
            'restaurant' => 'catering.restaurant',

            'fast_food' => 'catering.fast_food',

            'cafe' => 'catering.cafe',

            'bar' => 'catering.bar,catering.pub',

            'all' => implode(',', [
                'catering.restaurant',
                'catering.fast_food',
                'catering.cafe',
                'catering.bar',
                'catering.pub',
                'catering.ice_cream',
            ]),

            default => implode(',', [
                'catering.restaurant',
                'catering.fast_food',
                'catering.cafe',
                'catering.bar',
                'catering.pub',
                'catering.ice_cream',
            ]),
        };

        /*
        |--------------------------------------------------------------------------
        | Geoapify request
        |--------------------------------------------------------------------------
        |
        | filter = geographical search area
        | bias   = prioritize places close to the selected location
        | name   = search term entered by the user
        |
        */

        $params = [
            'categories' => $categories,

            'filter' => "circle:{$lng},{$lat},{$radius}",

            'bias' => "proximity:{$lng},{$lat}",

            'limit' => 50,

            'apiKey' => $apiKey,
        ];

        /*
        |--------------------------------------------------------------------------
        | Search term
        |--------------------------------------------------------------------------
        */

        $query = trim($query);

        if ($query !== '') {
            $params['name'] = $query;
        }

        /*
        |--------------------------------------------------------------------------
        | Send request to Geoapify
        |--------------------------------------------------------------------------
        */

        $response = Http::timeout(20)
            ->acceptJson()
            ->get($this->baseUrl, $params);

        /*
        |--------------------------------------------------------------------------
        | Handle failed request
        |--------------------------------------------------------------------------
        */

        if ($response->failed()) {
            throw new RuntimeException(
                'Geoapify request failed: ' .
                $response->status() .
                ' - ' .
                $response->body()
            );
        }

        $data = $response->json();

        $features = $data['features'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | Convert Geoapify results into DineHub format
        |--------------------------------------------------------------------------
        */

        return collect($features)
            ->map(function ($feature) {

                $properties = $feature['properties'] ?? [];

                $geometry = $feature['geometry'] ?? [];

                $coordinates = $geometry['coordinates'] ?? [];

                $lng = $coordinates[0] ?? null;

                $lat = $coordinates[1] ?? null;

                /*
                | Skip places without coordinates.
                */

                if ($lat === null || $lng === null) {
                    return null;
                }

                $categories = $properties['categories'] ?? [];

                /*
                | Geoapify's place ID is used as DineHub's
                | external restaurant identifier.
                */

                $placeId = $properties['place_id'] ?? null;

                /*
                | Build a clean address.
                */

                $address =
                    $properties['formatted']
                    ?? $properties['address_line1']
                    ?? $properties['address_line2']
                    ?? 'Address unavailable';

                return [

                    /*
                    |--------------------------------------------------------------------------
                    | Identification
                    |--------------------------------------------------------------------------
                    */

                    'fsq_place_id' => $placeId,

                    /*
                    |--------------------------------------------------------------------------
                    | Basic information
                    |--------------------------------------------------------------------------
                    */

                    'name' =>
                        $properties['name']
                        ?? $properties['address_line1']
                        ?? 'Unknown Restaurant',

                    'latitude' => (float) $lat,

                    'longitude' => (float) $lng,

                    /*
                    |--------------------------------------------------------------------------
                    | Categories
                    |--------------------------------------------------------------------------
                    */

                    'categories' => $categories,

                    /*
                    |--------------------------------------------------------------------------
                    | Address
                    |--------------------------------------------------------------------------
                    */

                    'address' => $address,

                    'city' => $properties['city'] ?? null,

                    'country' => $properties['country'] ?? null,

                    'postcode' => $properties['postcode'] ?? null,

                    /*
                    |--------------------------------------------------------------------------
                    | Contact information
                    |--------------------------------------------------------------------------
                    */

                    'website' => $properties['website'] ?? null,

                    'phone' =>
                        $properties['contact']['phone']
                        ?? $properties['phone']
                        ?? null,

                    /*
                    |--------------------------------------------------------------------------
                    | Photos
                    |--------------------------------------------------------------------------
                    |
                    | Geoapify does not guarantee a photo for every place.
                    | Return an empty array when none is available.
                    |
                    */

                    'photos' => $this->extractPhotos($properties),

                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Get details for a single Geoapify place.
     *
     * This method is intentionally named "details"
     * because RestaurantController calls:
     *
     * $this->geoapify->details($fsqId)
     */
    public function details(string $placeId): ?array
    {
        $apiKey = config('services.geoapify.key');

        if (!$apiKey) {
            throw new RuntimeException(
                'GEOAPIFY_API_KEY is not configured.'
            );
        }

        $response = Http::timeout(20)
            ->acceptJson()
            ->get(
                $this->baseUrl . '/details',
                [
                    'id' => $placeId,
                    'apiKey' => $apiKey,
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | Handle API error
        |--------------------------------------------------------------------------
        */

        if ($response->failed()) {
            throw new RuntimeException(
                'Geoapify details request failed: ' .
                $response->status()
            );
        }

        $data = $response->json();

        $feature = $data['features'][0] ?? null;

        if (!$feature) {
            return null;
        }

        $properties = $feature['properties'] ?? [];

        $geometry = $feature['geometry'] ?? [];

        $coordinates = $geometry['coordinates'] ?? [];

        $lat = $coordinates[1] ?? 0;

        $lng = $coordinates[0] ?? 0;

        return [

            /*
            |--------------------------------------------------------------------------
            | Identification
            |--------------------------------------------------------------------------
            */

            'fsq_place_id' =>
                $properties['place_id']
                ?? $placeId,

            /*
            |--------------------------------------------------------------------------
            | Basic information
            |--------------------------------------------------------------------------
            */

            'name' =>
                $properties['name']
                ?? $properties['address_line1']
                ?? 'Unknown Restaurant',

            'latitude' => (float) $lat,

            'longitude' => (float) $lng,

            /*
            |--------------------------------------------------------------------------
            | Categories
            |--------------------------------------------------------------------------
            */

            'categories' =>
                $properties['categories']
                ?? [],

            /*
            |--------------------------------------------------------------------------
            | Location
            |--------------------------------------------------------------------------
            */

            'location' => [
                'formatted_address' =>
                    $properties['formatted']
                    ?? $properties['address_line1']
                    ?? null,
            ],

            'address' =>
                $properties['formatted']
                ?? $properties['address_line1']
                ?? null,

            'city' =>
                $properties['city']
                ?? null,

            'country' =>
                $properties['country']
                ?? null,

            'postcode' =>
                $properties['postcode']
                ?? null,

            /*
            |--------------------------------------------------------------------------
            | Contact information
            |--------------------------------------------------------------------------
            */

            'website' =>
                $properties['website']
                ?? null,

            'phone' =>
                $properties['contact']['phone']
                ?? $properties['phone']
                ?? null,

            /*
            |--------------------------------------------------------------------------
            | Photos
            |--------------------------------------------------------------------------
            */

            'photos' =>
                $this->extractPhotos($properties),
        ];
    }

    /**
     * Extract available image information from Geoapify.
     *
     * Not every Geoapify place contains photos.
     */
    protected function extractPhotos(array $properties): array
    {
        $photos = [];

        /*
        |--------------------------------------------------------------------------
        | Direct image URL
        |--------------------------------------------------------------------------
        */

        if (!empty($properties['image'])) {
            $photos[] = $properties['image'];
        }

        /*
        |--------------------------------------------------------------------------
        | Datasource image
        |--------------------------------------------------------------------------
        */

        $rawImage =
            $properties['datasource']['raw']['image']
            ?? null;

        if (is_string($rawImage) && $rawImage !== '') {
            $photos[] = $rawImage;
        }

        /*
        |--------------------------------------------------------------------------
        | Remove duplicates
        |--------------------------------------------------------------------------
        */

        return array_values(
            array_unique($photos)
        );
    }
}