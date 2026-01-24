<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Hotel;
use Illuminate\Http\Request;

class SuperAdminBookingController extends Controller
{
    /**
     * Get all bookings across all hotels (for super admin)
     */
    public function index()
    {
        $bookings = Booking::with(['room', 'hotel', 'bookingServices.service'])
            ->orderBy('created_at', 'desc')
            ->get();

        $hotels = Hotel::select('id', 'name')->get();

        return response()->json([
            'bookings' => $bookings,
            'hotels' => $hotels
        ]);
    }

    /**
     * Get a specific booking details
     */
    public function show(Booking $booking)
    {
        $booking->load(['room', 'hotel', 'bookingServices.service', 'createdBy']);
        
        return response()->json([
            'booking' => $booking
        ]);
    }


    /**
     * Get bookings for a specific hotel
     */
    public function getByHotel(Hotel $hotel)
    {
        $bookings = $hotel->bookings()
            ->with(['room', 'bookingServices.service'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'bookings' => $bookings,
            'hotel' => $hotel
        ]);
    }

    /**
     * Get booking statistics
     */
    public function statistics()
    {
        $totalBookings = Booking::count();
        $activeBookings = Booking::where('status', 'active')->count();
        $completedBookings = Booking::where('status', 'completed')->count();
        $cancelledBookings = Booking::where('status', 'cancelled')->count();
        $totalRevenue = Booking::sum('paid_amount');
        $pendingRevenue = Booking::sum('due_amount');

        return response()->json([
            'total_bookings' => $totalBookings,
            'active_bookings' => $activeBookings,
            'completed_bookings' => $completedBookings,
            'cancelled_bookings' => $cancelledBookings,
            'total_revenue' => $totalRevenue,
            'pending_revenue' => $pendingRevenue,
        ]);
    }
    
}
