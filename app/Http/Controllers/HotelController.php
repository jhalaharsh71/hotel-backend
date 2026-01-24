<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Hotel;
use App\Models\User;
use App\Models\Subscription;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class HotelController extends Controller
{
    public function getHotel(){

     $hotel =  Hotel::orderBy('id')->get();
     $country = Country::orderBy('id')->get();
     $state = State::orderBy('id')->get();
     $city = City::orderBy('id')->get();     
        return response()->json([
            'hotel' => $hotel,
            'country' => $country,
            'state' => $state,
            'city' => $city 

           
        ]);
    
    }

    public function storeHotel(Request $request){
     
     $data= $request->validate([
        //hotel table 
            'name' => 'required|string',
            'address' => 'required|string',
            'country' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'contact_no' => 'required|string|min:8|max:15',
            'pincode' => 'required|string|regex:/^[0-9]{5,8}$/',
        //users table
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:admin',
        //subscription
            // 'fee' => 'required|numeric|min:0',
        ]);
      


    DB::beginTransaction();

    try {
        $hotel = Hotel::create([
            'name'       => $data['name'],
            'address'    => $data['address'],
            'country'    => $data['country'],
            'city'       => $data['city'],
            'state'      => $data['state'],
            'pincode'    => $data['pincode'],
            'contact_no' => $data['contact_no'],
        ]);

        $user = User::create([
            'name'     => $hotel->name ,
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => 'admin',
            'hotel_id' => $hotel->id,
            'status'   => 1,
        ]);

        // $subscription = Subscription::create([
        //     'hotel_id'        => $hotel->id,
        //     'amount'          => $data['fee'],
        //     'currency'        => 'INR',
        //     'starts_at'       => now(),
        //     'ends_at'         => now()->addYear(), 
        //     'status'          => '1',
        //     'payment_status'  => 'paid',
        //     'payment_method'  => 'manual',
        //     'payment_reference' => null,
        // ]);

        DB::commit();

        return response()->json([
            'message' => 'Hotel created successfully',
            'hotel'   => $hotel,
            'admin'   => $user,
            // 'subscription' => $subscription,
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'message' => 'Hotel creation failed',
            'error'   => $e->getMessage(),
        ], 500);
    }
    }

        public function getHotelData($id)
        {
            $hotel = Hotel::findOrFail($id);
            $users = User::where('hotel_id', $id)->get();
            $bookings = Booking::where('hotel_id', $id)->get();
            $countries = Country::orderBy('id')->get();
            $states = State::orderBy('id')->get();
            $cities = City::orderBy('id')->get();

            return response()->json([
                'hotel' => $hotel,
                'users' => $users,
                'bookings' => $bookings,
                'countries' => $countries,
                'states' => $states,
                'cities' => $cities,
            ]);
        }

public function getHotelDashboard()
{
    $hotel = Hotel::count();
    $activeHotel = Hotel::where('status', 1)->count();
    $inactiveHotel = Hotel::where('status', 0)->count();
    
    // Get total revenue and bookings across all hotels (excluding cancelled)
    $totalRevenue = DB::table('bookings')
        ->where('status', '!=', 'cancelled')
        ->sum(DB::raw('CAST(total_amount as DECIMAL(10, 2))'));
    
    $totalBookings = Booking::count();
    $completedBookings = Booking::where('status', 'checkout')->count();
    $pendingBookings = Booking::where('status', 'pending')->count();
    
    // Get hotel-wise data with revenue and bookings
    $hotelStats = Hotel::select('id', 'name', 'status')
        ->withCount(['users', 'bookings'])
        ->with([
            'bookings' => function ($query) {
                $query->where('status', '!=', 'cancelled')
                    ->select('hotel_id', DB::raw('SUM(CAST(total_amount as DECIMAL(10, 2))) as revenue'), DB::raw('COUNT(*) as booking_count'))
                    ->groupBy('hotel_id');
            }
        ])
        ->get()
        ->map(function ($hotel) {
            $revenue = $hotel->bookings->sum('revenue') ?? 0;
            $bookingCount = $hotel->bookings_count;
            
            return [
                'id' => $hotel->id,
                'name' => $hotel->name,
                'status' => $hotel->status ? 'active' : 'inactive',
                'users' => $hotel->users_count,
                'bookings' => $bookingCount,
                'revenue' => $revenue,
                'avgRevenuePerBooking' => $bookingCount > 0 ? round($revenue / $bookingCount, 2) : 0,
            ];
        })
        ->sortByDesc('revenue');
    
    // Get top performing hotel
    $topHotel = $hotelStats->first();
    
    // Monthly revenue trend (last 12 months - excluding cancelled)
    $monthlyRevenue = DB::table('bookings')
        ->where('status', '!=', 'cancelled')
        ->select(
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
            DB::raw('SUM(CAST(total_amount as DECIMAL(10, 2))) as revenue'),
            DB::raw('COUNT(*) as bookings')
        )
        ->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
        ->orderBy('month', 'asc')
        ->get();
    
    // Booking status distribution
    $bookingStatus = Booking::select('status', DB::raw('COUNT(*) as count'))
        ->groupBy('status')
        ->get();
    
    return response()->json([
        'hotel' => $hotel,
        'activeHotel' => $activeHotel,
        'inactiveHotel' => $inactiveHotel,
        'totalRevenue' => (float) $totalRevenue ?? 0,
        'totalBookings' => $totalBookings,
        'completedBookings' => $completedBookings,
        'pendingBookings' => $pendingBookings,
        'hotelStats' => $hotelStats->values(),
        'topHotel' => $topHotel,
        'monthlyRevenue' => $monthlyRevenue,
        'bookingStatus' => $bookingStatus,
    ]);
}


public function toggleStatus($id)
{
    $hotel = Hotel::findOrFail($id);

    // Toggle hotel status
    $newStatus = !$hotel->status;
    $hotel->status = $newStatus;
    $hotel->save();

    // Get users of this hotel
    $users = User::where('hotel_id', $id)->get();

    // Update user status
    User::where('hotel_id', $id)->update([
        'status' => $newStatus
    ]);

    // 🔥 If hotel is disabled → logout ONLY those users
    if ($newStatus == 0) {
        foreach ($users as $user) {
            $user->tokens()->delete(); // Sanctum logout
        }
    }

    return response()->json([
        'message' => 'Hotel status updated and users logged out',
        'status' => $hotel->status,
    ]);
}


public function updateHotel(Request $request, $id)
{
    $data = $request->validate([
        'name' => 'required|string',
        'address' => 'required|string',
        'country' => 'required|string',
        'city' => 'required|string',
        'state' => 'required|string',
        'pincode' => 'required|string|regex:/^[0-9]{5,8}$/',
        'phone_number' => 'nullable|string|min:8|max:15',
    ]);

    $hotel = Hotel::findOrFail($id);

    $hotel->update([
        'name' => $data['name'],
        'address' => $data['address'],
        'country' => $data['country'],
        'city' => $data['city'],
        'state' => $data['state'],
        'pincode' => $data['pincode'],
        'contact_no' => $data['phone_number'] ?? $hotel->contact_no,
    ]);

    return response()->json([
        'message' => 'Hotel updated successfully',
        'hotel' => $hotel,
    ]);
}

public function deleteHotel($id)
{
    $hotel = Hotel::findOrFail($id);

    // Delete associated users first
    User::where('hotel_id', $id)->delete();

    // Delete the hotel
    $hotel->delete();

    return response()->json([
        'message' => 'Hotel deleted successfully',
    ]);
}

// Search cities with lazy loading
public function searchCities(Request $request)
{
    $search = $request->query('search', '');
    $limit = $request->query('limit', 10);
    
    $query = City::query();
    
    if ($search) {
        $query->where('name', 'LIKE', "%{$search}%");
    }
    
    $cities = $query->limit($limit)->get();
    
    return response()->json([
        'cities' => $cities->map(fn($city) => ['value' => $city->name, 'label' => $city->name])
    ]);
}

public function addUser(Request $request, $hotelId)
{
    $data = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:users,email',
        'password' => 'required|string|min:6|confirmed',
        'role' => 'required|in:admin,manager,staff',
    ]);

    $user = User::create([
        'name' => $data['name'],
        'email' => $data['email'],
        'password' => Hash::make($data['password']),
        'role' => $data['role'],
        'hotel_id' => $hotelId,
        'status' => 1,
    ]);

    return response()->json([
        'message' => 'User added successfully',
        'user' => $user,
    ], 201);
}

public function updateUser(Request $request, $hotelId, $userId)
{
    $data = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:users,email,' . $userId,
        'role' => 'required|in:admin,manager,staff',
        'password' => 'nullable|string|min:6|confirmed',
    ]);

    $user = User::where('hotel_id', $hotelId)->findOrFail($userId);

    $updateData = [
        'name' => $data['name'],
        'email' => $data['email'],
        'role' => $data['role'],
    ];

    if (!empty($data['password'])) {
        $updateData['password'] = Hash::make($data['password']);
    }

    $user->update($updateData);

    return response()->json([
        'message' => 'User updated successfully',
        'user' => $user,
    ]);
}

public function deleteUser($hotelId, $userId)
{
    $user = User::where('hotel_id', $hotelId)->findOrFail($userId);
    $user->delete();

    return response()->json([
        'message' => 'User deleted successfully',
    ]);
}

}
