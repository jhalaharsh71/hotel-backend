<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Room;
use App\Models\City;
use App\Models\Hotel;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;

class UserController extends Controller
{
    /**
     * Get authenticated user's profile info
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $bookings = Booking::where('created_by_user_id', $user->id)->get();
        $bookingCount = $bookings->count();
        $lastBooking = $bookings->sortByDesc('created_at')->first();
        $lastBookingDate = $lastBooking ? $lastBooking->created_at : null;
        $totalNights = $bookings->reduce(function($carry, $b) {
            if ($b->check_in_date && $b->check_out_date) {
                $in = Carbon::parse($b->check_in_date);
                $out = Carbon::parse($b->check_out_date);
                return $carry + $in->diffInDays($out);
            }
            return $carry;
        }, 0);
        $hotelCounts = $bookings->groupBy('hotel_id')->map->count();
        $mostFrequentHotelId = $hotelCounts->sortDesc()->keys()->first();
        $mostFrequentHotel = $mostFrequentHotelId ? Hotel::find($mostFrequentHotelId) : null;
        $totalGuests = 0;
        foreach ($bookings as $b) {
            $totalGuests += $b->guests ? $b->guests->count() : 0;
        }
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
                'booking_count' => $bookingCount,
                'last_booking_date' => $lastBookingDate,
                'most_frequent_hotel' => $mostFrequentHotel ? $mostFrequentHotel->name : null,
                'total_nights' => $totalNights,
                'total_guests' => $totalGuests,
                // Add more fields as needed
            ]
        ], 200);
    }
    /**
     * Search Cities
     * 
     * Dynamically search for cities based on user input
     * Returns limited results to avoid performance issues
     * Supports autocomplete/typeahead functionality
     * 
     * @param Request $request
     * @return JSON response with matching cities
     */
    public function searchCities(Request $request)
    {
        // Validate input
        $request->validate([
            'q' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:5|max:50',
        ]);

        $search = $request->query('q', '');
        $limit = $request->query('limit', 15);

        // Build query
        $query = City::select('id', 'name');

        // If search term provided, filter by name
        if (!empty($search)) {
            $query->where('name', 'LIKE', '%' . $search . '%');
        } else {
            // If no search, return first N cities (ordered alphabetically)
            $query->orderBy('name', 'ASC');
        }

        // Apply limit
        $cities = $query->limit($limit)->get();

        return response()->json([
            'message' => 'Cities found',
            'search' => $search,
            'total' => $cities->count(),
            'cities' => $cities->map(function ($city) {
                return [
                    'id' => $city->id,
                    'name' => $city->name,
                ];
            }),
        ], 200);
    }

    /**
     * Search Hotels by City and Dates
     * 
     * Allows users to search for available hotels in a city with check-in/check-out dates
     * Returns hotels that have available rooms for the requested dates and guest count
     * 
     * @param Request $request
     * @return JSON response with matching hotels
     */
    public function searchHotels(Request $request)
    {
        // Validate input
        $request->validate([
            'city' => 'required|string',
            'check_in_date' => 'required|date|date_format:Y-m-d',
            'check_out_date' => 'required|date|date_format:Y-m-d|after:check_in_date',
            'no_of_people' => 'required|integer|min:1',
        ]);

        $city = $request->city;
        $checkInDate = $request->check_in_date;
        $checkOutDate = $request->check_out_date;
        $noOfPeople = $request->no_of_people;

        // Find hotels in the requested city with available rooms
        $hotels = Hotel::where('city', $city)
            ->where('status', 1) // Only active hotels
            ->with([
                'rooms' => function ($q) use ($noOfPeople) {
                    $q->where(function ($query) use ($noOfPeople) {
                        $query->whereRaw('min_people <= ?', [$noOfPeople])
                              ->whereRaw('max_people >= ?', [$noOfPeople]);
                    });
                }
            ])
            ->get()
            ->filter(function ($hotel) use ($noOfPeople, $checkInDate, $checkOutDate) {
                // Filter hotels that have at least one available room
                return $hotel->rooms->filter(function ($room) use ($checkInDate, $checkOutDate) {
                    // Check if this room has no overlapping confirmed bookings
                    $hasBooking = \App\Models\Booking::where('room_id', $room->id)
                        ->where('confirm_booking', true)
                        ->whereIn('status', ['active', 'check-in'])
                        ->where('check_in_date', '<', $checkOutDate)
                        ->where('check_out_date', '>', $checkInDate)
                        ->exists();
                    
                    return !$hasBooking;
                })->count() > 0;
            })
            ->values();

        if ($hotels->isEmpty()) {
            return response()->json([
                'message' => 'No hotels available in ' . $city . ' for the selected dates',
                'hotels' => [],
            ], 200);
        }

        // Format hotel data (limit fields for listing)
        $formattedHotels = $hotels->map(function ($hotel) {
            return [
                'id' => $hotel->id,
                'name' => $hotel->name,
                'city' => $hotel->city,
                'address' => $hotel->address,
                'contact_no' => $hotel->contact_no,
                'available_rooms_count' => $hotel->rooms->count(),
            ];
        });

        return response()->json([
            'message' => 'Hotels found',
            'city' => $city,
            'check_in_date' => $checkInDate,
            'check_out_date' => $checkOutDate,
            'no_of_people' => $noOfPeople,
            'hotels' => $formattedHotels,
            'total_hotels' => $formattedHotels->count(),
        ], 200);
    }

    /**
     * Get Hotel Details with Rooms
     * 
     * Returns complete hotel information including all room types
     * Used for the hotel details page during booking flow
     * 
     * @param int $hotelId
     * @param Request $request
     * @return JSON response with hotel details and rooms
     */
  public function getHotelDetails($hotelId, Request $request)
{
    $request->validate([
        'check_in_date' => 'nullable|date|date_format:Y-m-d',
        'check_out_date' => 'nullable|date|date_format:Y-m-d|after:check_in_date',
        'no_of_people' => 'nullable|integer|min:1',
    ]);

    $hotel = Hotel::findOrFail($hotelId);

    $checkInDate = $request->check_in_date;
    $checkOutDate = $request->check_out_date;
    $noOfPeople = $request->no_of_people;

    $roomsQuery = Room::where('hotel_id', $hotelId);

    // ✅ Apply availability filter ONLY if dates are provided
    if ($checkInDate && $checkOutDate) {
        $roomsQuery->whereDoesntHave('bookings', function ($q) use ($checkInDate, $checkOutDate) {
            $q->where('confirm_booking', true)
              ->whereNotIn('status', ['cancelled', 'checkout'])
              ->where('check_in_date', '<', $checkOutDate)
              ->where('check_out_date', '>', $checkInDate);
        });
    }

    // ✅ Capacity filter
    if ($noOfPeople) {
        $roomsQuery->where('min_people', '<=', $noOfPeople)
                   ->where('max_people', '>=', $noOfPeople);
    }

    $rooms = $roomsQuery->get();

    $formattedRooms = $rooms->map(function ($room) {
        return [
            'id' => $room->id,
            'room_number' => $room->room_number,
            'room_type' => $room->room_type,
            'price_per_day' => (float) $room->price,
            'min_people' => $room->min_people,
            'max_people' => $room->max_people,
        ];
    });

    return response()->json([
        'message' => 'Hotel details retrieved',
        'hotel' => [
            'id' => $hotel->id,
            'name' => $hotel->name,
            'address' => $hotel->address,
            'city' => $hotel->city,
            'country' => $hotel->country,
            'state' => $hotel->state,
            'contact_no' => $hotel->contact_no,
        ],
        'rooms' => $formattedRooms,
        'total_available_rooms' => $formattedRooms->count(),
    ], 200);
}


    /**
     * Check Room Availability
     * 
     * Validates dates and returns available hotels with room types and pricing
     * Filters rooms and hotels based on city, dates, and room capacity (min_people, max_people)
     * Shows hotel details along with available rooms from that hotel
     * 
     * Supports optional filters:
     * - price_min: Minimum room price filter
     * - price_max: Maximum room price filter
     * - room_type: Filter by specific room type
     * - rating: Minimum hotel rating filter
     * 
     * @param Request $request
     * @return JSON response with available hotels and their room types or error
     */
    public function checkRoomAvailability(Request $request)
    {
        // Validate input
        $request->validate([
            'city' => 'required|string',
            'check_in_date' => 'required|date|date_format:Y-m-d',
            'check_out_date' => 'required|date|date_format:Y-m-d|after:check_in_date',
            'no_of_people' => 'required|integer|min:1',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'room_type' => 'nullable|string|max:100',
            'rating' => 'nullable|numeric|min:0|max:5',
        ]);

        $city = $request->city;
        $checkInDate = $request->check_in_date;
        $checkOutDate = $request->check_out_date;
        $noOfPeople = $request->no_of_people;
        
        // FILTER PARAMETERS
        $priceMin = $request->input('price_min', 0);
        $priceMax = $request->input('price_max', PHP_INT_MAX);
        $roomTypeFilter = $request->input('room_type', '');
        $ratingFilter = $request->input('rating', 0);

        // Calculate number of days for this booking
        $checkIn = new \DateTime($checkInDate);
        $checkOut = new \DateTime($checkOutDate);
        $interval = $checkIn->diff($checkOut);
        $numberOfDays = $interval->days;

        // Ensure minimum 1 day
        if ($numberOfDays < 1) {
            $numberOfDays = 1;
        }

        // Find all rooms that are available for the requested date range and city
        // Available rooms = rooms that do NOT have overlapping ACTIVE bookings (regardless of status)
        // AND satisfy the no_of_people capacity constraint (min_people <= no_of_people <= max_people)
        // AND belong to hotels in the searched city
        $availableRooms = Room::whereDoesntHave('bookings', function ($q) use ($checkInDate, $checkOutDate) {
                $q->where('confirm_booking', true)
                ->whereNotIn('status', ['cancelled', 'checkout'])
                ->where('check_in_date', '<', $checkOutDate)
                ->where('check_out_date', '>', $checkInDate);

            })
            ->whereHas('hotel', function ($q) use ($city) {
                $q->where('city', $city)
                ->where('status', 1);
            })
            ->where('min_people', '<=', $noOfPeople)
            ->where('max_people', '>=', $noOfPeople)
            // FILTER: Apply price filter
            ->whereBetween('price', [$priceMin, $priceMax])
            // FILTER: Apply room type filter (if provided)
            ->when($roomTypeFilter, function ($query, $roomType) {
                return $query->where('room_type', $roomType);
            })
            ->with('hotel')
            ->select('id', 'hotel_id', 'room_type', 'room_number', 'price')
            ->get();


        if ($availableRooms->isEmpty()) {
            return response()->json([
                'message' => 'No rooms available in ' . $city . ' for selected dates and guest count',
                'available_hotels' => [],
                'duration_days' => $numberOfDays,
            ], 200);
        }
          
        // Group rooms by hotel to show hotel details with their available rooms
        $hotelGroups = $availableRooms->groupBy('hotel_id')->map(function ($rooms) {
            $hotel = $rooms->first()->hotel;
            
            // Fetch active gallery images including banner image for this hotel
            $galleries = DB::table('hotel_galleries')
                ->where('hotel_id', $hotel->id)
                ->where('is_active', true)
                ->select('id', 'image_path', 'is_banner_image', 'is_active')
                ->get();
            
            // Group rooms by room_type within this hotel
            $roomTypes = $rooms->groupBy('room_type')->map(function ($roomGroup) {
                $firstRoom = $roomGroup->first();
                return [
                    'room_ids' => $roomGroup->pluck('id'),
                    'room_type' => $firstRoom->room_type,
                    'price_per_day' => (float) $firstRoom->price,
                    'available_count' => $roomGroup->count(),
                ];
            })->values();

            return [
                'hotel_id' => $hotel->id,
                'hotel_name' => $hotel->name,
                'hotel_city' => $hotel->city,
                'hotel_address' => $hotel->address,
                'hotel_contact' => $hotel->contact_no,
                'room_types' => $roomTypes,
                'total_rooms_in_hotel' => $rooms->count(),
                'galleries' => $galleries,
            ];
        })->values();
        
        // FILTER: Apply rating filter (filter at collection level after grouping)
        // Hotels without reviews are shown in all rating filters
        if ($ratingFilter > 0) {
            $hotelGroups = $hotelGroups->filter(function ($hotelGroup) use ($ratingFilter) {
                // Get average rating for this hotel from reviews
                $hotel = Hotel::find($hotelGroup['hotel_id']);
                if (!$hotel) return false;
                
                $reviews = DB::table('reviews')
                    ->where('hotel_id', $hotel->id)
                    ->get();
                
                // If no reviews, show in all rating filters
                if ($reviews->isEmpty()) {
                    return true;
                }
                
                $avgRating = $reviews->avg('rating');
                return $avgRating >= $ratingFilter;
            })->values();
        }

        return response()->json([
            'message' => 'Rooms available',
            'city' => $city,
            'check_in_date' => $checkInDate,
            'check_out_date' => $checkOutDate,
            'no_of_people' => $noOfPeople,
            'duration_days' => $numberOfDays,
            'available_hotels' => $hotelGroups,
            'total_hotels' => $hotelGroups->count(),
            'total_rooms_available' => $availableRooms->count(),
        ], 200);
    }

    /**
     * Create User Booking
     * 
     * Creates a new booking with server-side price validation
     * 
     * @param Request $request
     * @return JSON response with booking details or error
     */
    public function createBooking(Request $request)
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json([
                'message' => 'Please login first to create a booking',
            ], 401);
        }

        // Validate input
        $request->validate([
            'hotel_id' => 'required|exists:hotels,id',
            'customer_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'check_in_date' => 'required|date|date_format:Y-m-d',
            'check_out_date' => 'required|date|date_format:Y-m-d|after:check_in_date',
            'room_id' => 'required|exists:rooms,id',
            'no_of_people' => 'required|integer|min:1',
            'paid' => 'required|numeric|min:0',
            'mode_of_payment' => 'required|in:Cash,Card,UPI',
            'online_payment_status' => 'nullable|in:success,failed', // Optional payment status from dummy gateway
            // Guests array validation (required to match no_of_people)
            'guests' => 'required|array',
            'guests.*.first_name' => 'required|string|max:255',
            'guests.*.last_name' => 'required|string|max:255',
            'guests.*.gender' => 'required|string|max:50',
            'guests.*.age' => 'required|integer|min:0|max:150',
            'guests.*.phone' => 'required|string|max:20',
            'guests.*.email' => 'required|email|max:255',
        ]);

        $checkInDate = $request->check_in_date;
        $checkOutDate = $request->check_out_date;
        $roomId = $request->room_id;

        // ===== PAYMENT GATEWAY VALIDATION =====
        // For UPI and Card payments, only allow booking if payment was successful (dummy gateway)
        $onlinePaymentStatus = $request->input('online_payment_status');
        $paymentMode = $request->mode_of_payment;

        if (($paymentMode === 'Card' || $paymentMode === 'UPI') && $onlinePaymentStatus !== 'success') {
            return response()->json([
                'message' => 'Payment must be successful to create a booking. Please complete the payment process.',
                'payment_mode' => $paymentMode,
                'online_payment_status' => $onlinePaymentStatus,
            ], 402); // 402 Payment Required
        }

        // ===== RE-VALIDATE ON BACKEND (NEVER TRUST FRONTEND) =====

        // 1. Check room exists
        $room = Room::findOrFail($roomId);

        // 2. Check room availability (prevent double booking)
        $isAvailable = !Booking::where('room_id', $roomId)
            ->where('confirm_booking', true)
            ->where('status', 'active')
            ->where('check_in_date', '<', $checkOutDate)
            ->where('check_out_date', '>', $checkInDate)
            ->exists();

        if (!$isAvailable) {
            return response()->json([
                'message' => 'Room is not available for selected dates',
            ], 409);
        }

        // 3. Validate no_of_people fits room capacity (min_people <= no_of_people <= max_people)
        $noOfPeople = $request->no_of_people;
        if ($noOfPeople < $room->min_people || $noOfPeople > $room->max_people) {
            return response()->json([
                'message' => "This room accommodates {$room->min_people} to {$room->max_people} people. Your party size of {$noOfPeople} is not suitable for this room.",
                'room_capacity' => [
                    'min_people' => $room->min_people,
                    'max_people' => $room->max_people,
                    'requested_people' => $noOfPeople,
                ],
            ], 422);
        }

        // 4. Calculate duration again (server-side calculation is authoritative)
        $checkIn = new \DateTime($checkInDate);
        $checkOut = new \DateTime($checkOutDate);
        $interval = $checkIn->diff($checkOut);
        $numberOfDays = $interval->days;

        if ($numberOfDays < 1) {
            $numberOfDays = 1;
        }

        // 5. Calculate total amount on backend (NEVER trust frontend calculation)
        $totalAmount = $numberOfDays * $room->price;

        // 6. Validate paid amount does not exceed total and meets minimum 10% advance
        $paidAmount = (float) $request->paid;
        $minimumAdvancePayment = $totalAmount * 0.10; // 10% of total amount
        
        if ($paidAmount < $minimumAdvancePayment) {
            return response()->json([
                'message' => 'Minimum advance payment of 10% is required to complete booking',
                'total_amount' => $totalAmount,
                'minimum_advance_payment' => $minimumAdvancePayment,
                'paid_amount' => $paidAmount,
            ], 422);
        }
        
        if ($paidAmount > $totalAmount) {
            return response()->json([
                'message' => 'Paid amount cannot exceed total amount',
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
            ], 422);
        }

        // 7. Calculate due amount
        $dueAmount = $totalAmount - $paidAmount;
        if ($dueAmount < 0) {
            $dueAmount = 0;
        }

        // ===== CREATE BOOKING + GUESTS WITH TRANSACTION =====
        // We already validated guests above. Now ensure guest count matches no_of_people
        $guests = $request->input('guests', []);
        if (count($guests) !== (int) $request->no_of_people) {
            return response()->json([
                'message' => 'Number of guests must match no_of_people',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create booking
            $booking = Booking::create([
                'hotel_id' => $request->hotel_id,
                'customer_name' => $request->customer_name,
                'phone' => $request->phone,
                'email' => $request->email,
                'check_in_date' => $checkInDate,
                'check_out_date' => $checkOutDate,
                'room_id' => $roomId,
                'no_of_people' => $request->no_of_people,
                'confirm_booking' => false,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'mode_of_payment' => $request->mode_of_payment,
                'online_payment_status' => $onlinePaymentStatus,
                'status' => 'Pending',
                'created_by_user_id' => $request->user()->id,
            ]);

            // Insert guest records linked to booking (atomic)
            foreach ($guests as $index => $g) {
                // Insert directly using query builder to avoid mass-assignment issues
                DB::table('guests')->insert([
                    'booking_id' => $booking->id,
                    'first_name' => $g['first_name'],
                    'last_name' => $g['last_name'],
                    'gender' => $g['gender'],
                    'age' => (int) $g['age'],
                    // Do NOT collect or auto-fill ID details — store NULL explicitly
                    'id_type' => null,
                    'id_number' => null,
                    'phone' => $g['phone'],
                    'email' => $g['email'],
                    'is_primary' => $index === 0 ? true : false,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Booking created successfully',
                'booking_id' => $booking->id,
                'customer_name' => $booking->customer_name,
                'check_in_date' => $booking->check_in_date,
                'check_out_date' => $booking->check_out_date,
                'duration_days' => $numberOfDays,
                'room_number' => $room->room_number,
                'room_type' => $room->room_type,
                'price_per_day' => $room->price,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'mode_of_payment' => $request->mode_of_payment,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error creating booking or guests',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get User Bookings
     * 
     * Retrieves all bookings created by the authenticated user
     * Loads hotel and room relationships for complete booking details
     * 
     * @param Request $request
     * @return JSON response with user bookings
     */
    public function getUserBookings(Request $request)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $userId = $request->user()->id;

        $bookings = Booking::where('created_by_user_id', $userId)
            ->with(['room', 'hotel'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'User bookings retrieved successfully',
            'bookings' => $bookings,
            'total' => $bookings->count(),
        ], 200);
    }

    /**
     * Get Single User Booking
     * 
     * Retrieves details of a specific booking for the authenticated user
     * 
     * @param Request $request
     * @param int $id
     * @return JSON response with booking details
     */
    public function getUserBooking(Request $request, $id)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $userId = $request->user()->id;

        $booking = Booking::where('id', $id)
            ->where('created_by_user_id', $userId)
            ->with('room')
            ->firstOrFail();

        return response()->json($booking, 200);
    }

    /**
     * Update User Booking Details
     * 
     * Allows users to update guest details (name, phone, email)
     * Only allows updates for pending or active bookings
     * 
     * @param Request $request
     * @param int $id
     * @return JSON response
     */
    public function updateUserBooking(Request $request, $id)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $userId = $request->user()->id;

        $booking = Booking::where('id', $id)
            ->where('created_by_user_id', $userId)
            ->firstOrFail();

        // Only allow updates for pending or active bookings
        if ($booking->status !== 'pending' && $booking->status !== 'active') {
            return response()->json([
                'message' => 'Cannot modify booking details for ' . $booking->status . ' bookings',
            ], 422);
        }

        $request->validate([
            'customer_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
        ]);

        $booking->update([
            'customer_name' => $request->customer_name,
            'phone' => $request->phone,
            'email' => $request->email,
        ]);

        return response()->json([
            'message' => 'Booking details updated successfully',
            'booking' => $booking,
        ], 200);
    }

    /**
     * Cancel User Booking
     * 
     * Allows users to cancel their bookings
     * Only allows cancellation for pending or active bookings
     * 
     * @param Request $request
     * @param int $id
     * @return JSON response
     */
    public function cancelUserBooking(Request $request, $id)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $userId = $request->user()->id;

        $booking = Booking::where('id', $id)
            ->where('created_by_user_id', $userId)
            ->firstOrFail();

        // Only allow cancellation for pending or active bookings
        if ($booking->status !== 'pending' && $booking->status !== 'active') {
            return response()->json([
                'message' => 'Cannot cancel ' . $booking->status . ' bookings',
            ], 422);
        }

        $booking->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'message' => 'Booking cancelled successfully',
            'booking' => $booking,
        ], 200);
    }

    /**
     * Get User Booking Services
     * 
     * Retrieves all services associated with a specific booking
     * 
     * @param Request $request
     * @param int $id
     * @return JSON response with booking services
     */
    public function getUserBookingServices(Request $request, $id)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $userId = $request->user()->id;

        // Verify booking belongs to user
        $booking = Booking::where('id', $id)
            ->where('created_by_user_id', $userId)
            ->firstOrFail();

        // Get booking services with service details
        $services = \App\Models\BookingService::where('booking_id', $id)
            ->with('service')
            ->get();

        return response()->json($services, 200);
    }

    /**
     * Retrieves all guests associated with a specific booking
     * 
     * @param Request $request
     * @param int $id
     * @return JSON response with booking guests
     */
    public function getUserBookingGuests(Request $request, $id)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $userId = $request->user()->id;

        // Verify booking belongs to user
        $booking = Booking::where('id', $id)
            ->where('created_by_user_id', $userId)
            ->firstOrFail();

        // Get all guests for this booking
        $guests = \App\Models\Guest::where('booking_id', $id)
            ->get();

        return response()->json($guests, 200);
    }

    /**
     * Get all guests associated with ANY booking created by the authenticated user
     * Implements: fetch bookings by user -> collect booking ids -> fetch guests where booking_id in booking ids
     * Filters out incomplete guest records (required fields must be present)
     */
    public function getUserGuestsAll(Request $request)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $userId = $request->user()->id;

        // Fetch bookings belonging to this user (created_by_user_id)
        $bookingIds = Booking::where('created_by_user_id', $userId)->pluck('id')->toArray();

        if (empty($bookingIds)) {
            return response()->json([
                'message' => 'No guests found',
                'guests' => [],
            ], 200);
        }

        // Fetch guests linked to these bookings
        $guests = \App\Models\Guest::whereIn('booking_id', $bookingIds)
            ->where('status', 'active')
            ->get();

        // Filter to only include complete guest records
        $required = ['first_name', 'last_name', 'gender', 'age', 'phone', 'email'];
        $filtered = $guests->filter(function ($g) use ($required) {
            foreach ($required as $f) {
                if (!isset($g->$f) || $g->$f === null || (is_string($g->$f) && trim($g->$f) === '')) {
                    return false;
                }
            }
            return true;
        })->values();

        return response()->json([
            'message' => 'User guests retrieved',
            'guests' => $filtered,
            'total' => $filtered->count(),
        ], 200);
    }
}