<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\HotelGallery;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MapController extends Controller
{
    /**
     * Get all hotels with map data (name, lat, lng, lowest price)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
public function getAllHotels()
{
    try {
        $hotels = Hotel::where('status', 1)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select([
                'id',
                'name',
                'city',
                'latitude',
                'longitude'
            ])
            ->with([
                'rooms' => function ($query) {
                    $query->where('status', 1)
                          ->select('id', 'hotel_id', 'price');
                },
                'hotel_galleries' => function ($query) {
                    $query->select('hotel_id', 'image_path')
                          ->where('is_active', true)
                          ->where('is_banner_image', true);
                }
            ])
            ->withAvg(
            ['reviews as average_rating' => function ($query) {
                $query->where('status', 'approved');
            }],
            'rating'
        )

            ->get()
            ->map(function ($hotel) {

                $lowestPrice = $hotel->rooms->count() > 0
                    ? $hotel->rooms->min('price')
                    : null;

                return [
    'id' => $hotel->id,
    'name' => $hotel->name,
    'city' => $hotel->city,
    'latitude' => (float) $hotel->latitude,
    'longitude' => (float) $hotel->longitude,
    'lowest_price' => $lowestPrice ? (int) $lowestPrice : null,
    'average_rating' => $hotel->average_rating
        ? round((float) $hotel->average_rating, 1)
        : null,

    // ✅ ADD IMAGE
    'image' => $hotel->hotel_galleries->first()
        ? asset($hotel->hotel_galleries->first()->image_path)
        : asset('images/default-hotel.jpg'),
];
            })
            ->values();

        return response()->json([
            'status' => 'success',
            'message' => 'Hotels retrieved successfully',
            'total' => $hotels->count(),
            'data' => $hotels,
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to retrieve hotels',
            'error' => $e->getMessage(),
        ], 500);
    }
}


    /**
     * Get hotels within a certain radius of given latitude and longitude
     * Uses Haversine formula to calculate distance
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNearbyHotels(Request $request)
    {
        try {
            $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radius' => 'nullable|numeric|min:1|max:1000', // radius in km
            ]);

            $userLat = (float) $request->latitude;
            $userLng = (float) $request->longitude;
            $radius = (float) ($request->radius ?? 10); // default 10 km

            // Fetch all active hotels with coordinates
            $hotels = Hotel::where('status', 1)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->select([
                    'id',
                    'name',
                    'city',
                    'latitude',
                    'longitude'
                ])
                ->with([
                    'rooms' => function ($query) {
                        $query->where('status', 1)
                              ->select('id', 'hotel_id', 'price');
                    }
                ])
                ->get()
                ->map(function ($hotel) {
                    $lowestPrice = $hotel->rooms->count() > 0 
                        ? $hotel->rooms->min('price') 
                        : null;

                    return [
                        'id' => $hotel->id,
                        'name' => $hotel->name,
                        'city' => $hotel->city,
                        'latitude' => (float) $hotel->latitude,
                        'longitude' => (float) $hotel->longitude,
                        'lowest_price' => $lowestPrice ? (int) $lowestPrice : null,
                    ];
                });

            // Filter hotels within radius using Haversine formula
            $nearbyHotels = $hotels->filter(function ($hotel) use ($userLat, $userLng, $radius) {
                $distance = $this->haversineDistance($userLat, $userLng, $hotel['latitude'], $hotel['longitude']);
                return $distance <= $radius;
            })
            ->map(function ($hotel) use ($userLat, $userLng) {
                $hotel['distance'] = $this->haversineDistance($userLat, $userLng, $hotel['latitude'], $hotel['longitude']);
                return $hotel;
            })
            ->sortBy('distance')
            ->values();

            return response()->json([
                'status' => 'success',
                'message' => 'Nearby hotels retrieved successfully',
                'radius_km' => $radius,
                'user_location' => [
                    'latitude' => $userLat,
                    'longitude' => $userLng,
                ],
                'total' => $nearbyHotels->count(),
                'data' => $nearbyHotels,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve nearby hotels',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get hotels by city with map data
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getHotelsByCity(Request $request)
    {
        try {
            $request->validate([
                'city' => 'required|string|max:100',
            ]);

            $city = $request->city;

            $hotels = Hotel::where('status', 1)
                ->where('city', $city)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->select([
                    'id',
                    'name',
                    'city',
                    'latitude',
                    'longitude'
                ])
                ->with([
                    'rooms' => function ($query) {
                        $query->where('status', 1)
                              ->select('id', 'hotel_id', 'price');
                    }
                ])
                ->get()
                ->map(function ($hotel) {
                    $lowestPrice = $hotel->rooms->count() > 0 
                        ? $hotel->rooms->min('price') 
                        : null;

                    return [
                        'id' => $hotel->id,
                        'name' => $hotel->name,
                        'city' => $hotel->city,
                        'latitude' => (float) $hotel->latitude,
                        'longitude' => (float) $hotel->longitude,
                        'lowest_price' => $lowestPrice ? (int) $lowestPrice : null,
                    ];
                })
                ->values();

            return response()->json([
                'status' => 'success',
                'message' => 'Hotels in ' . $city . ' retrieved successfully',
                'city' => $city,
                'total' => $hotels->count(),
                'data' => $hotels,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve hotels by city',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     * Returns distance in kilometers
     * 
     * @param float $lat1 - User latitude
     * @param float $lon1 - User longitude
     * @param float $lat2 - Hotel latitude
     * @param float $lon2 - Hotel longitude
     * @return float - Distance in kilometers
     */
    private function haversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadiusKm = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadiusKm * $c;

        return round($distance, 2);
    }
}
