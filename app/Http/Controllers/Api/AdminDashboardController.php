<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Models\HotelService;
use App\Models\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $hotelId = auth()->user()->hotel_id;
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // === ROOMS METRICS ===
        $totalRooms = Room::where('hotel_id', $hotelId)->count();
        $activeRooms = Room::where('hotel_id', $hotelId)->where('status', 1)->count();
        $inactiveRooms = Room::where('hotel_id', $hotelId)->where('status', 0)->count();
        $occupiedRooms = Room::whereHas('bookings', function ($query) use ($today) {
            $query->where('check_in_date', '<=', $today)
                  ->where('check_out_date', '>=', $today);
        })->where('hotel_id', $hotelId)->count();
        $availableRooms = $totalRooms - $occupiedRooms;
        $occupancyPercentage = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 2) : 0;

        // === SERVICES METRICS ===
        $totalServices = HotelService::where('hotel_id', $hotelId)->count();
        $activeServices = HotelService::where('hotel_id', $hotelId)->where('status', 1)->count();

        // === BOOKINGS METRICS ===
        $totalBookings = Booking::where('hotel_id', $hotelId)->count();
        $confirmedBookings = Booking::where('hotel_id', $hotelId)->where('confirm_booking', '1')->count();
        $pendingBookings = Booking::where('hotel_id', $hotelId)->where('confirm_booking', '0')->count();
        $cancelledBookings = Booking::where('hotel_id', $hotelId)->where('status', 'cancelled')->count();
        
        $todayBookings = Booking::where('hotel_id', $hotelId)
            ->where('check_in_date', $today)
            ->count();
        
        $monthlyBookings = Booking::where('hotel_id', $hotelId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        // === REVENUE METRICS (Excluding Cancelled Bookings) ===
        $totalRevenue = Booking::where('hotel_id', $hotelId)
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');
        $totalPaidAmount = Booking::where('hotel_id', $hotelId)
            ->where('status', '!=', 'cancelled')
            ->sum('paid_amount');
        $totalDueAmount = Booking::where('hotel_id', $hotelId)
            ->where('status', '!=', 'cancelled')
            ->sum('due_amount');
        
        $todayRevenue = Booking::where('hotel_id', $hotelId)
            ->where('status', '!=', 'cancelled')
            ->where('check_in_date', $today)
            ->sum('total_amount');
        
        $monthlyRevenue = Booking::where('hotel_id', $hotelId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        // === SERVICES REVENUE ===
        $topServices = BookingService::selectRaw('hotel_services.name, COUNT(*) as count, SUM(booking_services.total_price) as total_revenue')
            ->join('hotel_services', 'booking_services.hotel_service_id', '=', 'hotel_services.id')
            ->where('hotel_services.hotel_id', $hotelId)
            ->groupBy('hotel_services.id', 'hotel_services.name')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        // === RECENT BOOKINGS ===
        $recentBookings = Booking::with(['room'])
            ->where('hotel_id', $hotelId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($booking) => [
                'id' => $booking->id,
                'customer_name' => $booking->customer_name,
                'email' => $booking->email,
                'phone' => $booking->phone,
                'room_number' => $booking->room?->room_number,
                'check_in_date' => $booking->check_in_date->format('Y-m-d'),
                'check_out_date' => $booking->check_out_date->format('Y-m-d'),
                'status' => $booking->status,
                'total_amount' => $booking->total_amount,
                'paid_amount' => $booking->paid_amount,
                'due_amount' => $booking->due_amount,
            ]);

        // === UPCOMING CHECK-INS (Next 7 days) ===
        $upcomingCheckIns = Booking::with(['room'])
            ->where('hotel_id', $hotelId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('check_in_date', [$today, $today->copy()->addDays(7)])
            ->orderBy('check_in_date', 'asc')
            ->limit(5)
            ->get()
            ->map(fn($booking) => [
                'id' => $booking->id,
                'customer_name' => $booking->customer_name,
                'room_number' => $booking->room?->room_number,
                'check_in_date' => $booking->check_in_date->format('Y-m-d'),
                'status' => $booking->status,
            ]);

        // === TODAY'S CHECKOUTS ===
        $todayCheckouts = Booking::with(['room'])
            ->where('hotel_id', $hotelId)
            ->where('status', '!=', 'cancelled')
            ->where('check_out_date', $today)
            ->orderBy('check_out_date', 'asc')
            ->limit(5)
            ->get()
            ->map(fn($booking) => [
                'id' => $booking->id,
                'customer_name' => $booking->customer_name,
                'room_number' => $booking->room?->room_number,
                'check_out_date' => $booking->check_out_date->format('Y-m-d'),
                'status' => $booking->status,
            ]);

        return response()->json([
            'rooms' => [
                'total' => $totalRooms,
                'active' => $activeRooms,
                'inactive' => $inactiveRooms,
                'occupied' => $occupiedRooms,
                'available' => $availableRooms,
                'occupancy_percentage' => $occupancyPercentage,
            ],
            'services' => [
                'total' => $totalServices,
                'active' => $activeServices,
            ],
            'bookings' => [
                'total' => $totalBookings,
                'confirmed' => $confirmedBookings,
                'pending' => $pendingBookings,
                'cancelled' => $cancelledBookings,
                'today' => $todayBookings,
                'this_month' => $monthlyBookings,
            ],
            'revenue' => [
                'total' => round($totalRevenue, 2),
                'paid' => round($totalPaidAmount, 2),
                'due' => round($totalDueAmount, 2),
                'today' => round($todayRevenue, 2),
                'this_month' => round($monthlyRevenue, 2),
            ],
            'top_services' => $topServices,
            'recent_bookings' => $recentBookings,
            'upcoming_check_ins' => $upcomingCheckIns,
            'today_checkouts' => $todayCheckouts,
        ]);
    }
}