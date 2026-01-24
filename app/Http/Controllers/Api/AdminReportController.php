<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Models\HotelService;
use App\Models\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    public function index(Request $request)
    {
        $hotelId = auth()->user()->hotel_id;
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        // Booking Summary
        $bookingSummary = $this->getBookingSummary($hotelId, $fromDate, $toDate);

        // Revenue Report
        $revenueReport = $this->getRevenueReport($hotelId, $fromDate, $toDate);

        // Room Report
        $roomReport = $this->getRoomReport($hotelId, $fromDate, $toDate);

        // Service Report
        $serviceReport = $this->getServiceReport($hotelId, $fromDate, $toDate);

        // Payment Report
        $paymentReport = $this->getPaymentReport($hotelId, $fromDate, $toDate);

        return response()->json([
            'booking_summary' => $bookingSummary,
            'revenue_report' => $revenueReport,
            'room_report' => $roomReport,
            'service_report' => $serviceReport,
            'payment_report' => $paymentReport,
        ]);
    }

    /**
     * Booking Summary Data
     */
    private function getBookingSummary($hotelId, $fromDate = null, $toDate = null)
    {
        $query = Booking::where('hotel_id', $hotelId);
        
        // Apply date filters
        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $totalBookings = $query->count();
        
        $confirmedBookings = Booking::where('hotel_id', $hotelId)
            ->where('confirm_booking', true);
        
        if ($fromDate) {
            $confirmedBookings->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $confirmedBookings->whereDate('created_at', '<=', $toDate);
        }
        $confirmedBookings = $confirmedBookings->count();
        
        $cancelledBookings = Booking::where('hotel_id', $hotelId)
            ->where('status', 'cancelled');
        
        if ($fromDate) {
            $cancelledBookings->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $cancelledBookings->whereDate('created_at', '<=', $toDate);
        }
        $cancelledBookings = $cancelledBookings->count();

        // Date-wise booking count
        $dateWiseBookingsQuery = Booking::where('hotel_id', $hotelId)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'asc');

        if ($fromDate) {
            $dateWiseBookingsQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $dateWiseBookingsQuery->whereDate('created_at', '<=', $toDate);
        }
        $dateWiseBookings = $dateWiseBookingsQuery->get();

        // Monthly trend
        $monthlyBookingsQuery = Booking::where('hotel_id', $hotelId)
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc');

        if ($fromDate) {
            $monthlyBookingsQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $monthlyBookingsQuery->whereDate('created_at', '<=', $toDate);
        }
        $monthlyBookings = $monthlyBookingsQuery->limit(12)->get();

        return [
            'total_bookings' => $totalBookings,
            'confirmed_bookings' => $confirmedBookings,
            'cancelled_bookings' => $cancelledBookings,
            'pending_bookings' => $totalBookings - $confirmedBookings,
            'date_wise_bookings' => $dateWiseBookings,
            'monthly_bookings' => $monthlyBookings,
        ];
    }

    /**
     * Revenue Report Data (Excluding Cancelled Bookings)
     */
    private function getRevenueReport($hotelId, $fromDate = null, $toDate = null)
    {
        $revenueQuery = Booking::where('hotel_id', $hotelId)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('SUM(total_amount) as total_revenue, SUM(paid_amount) as total_paid, SUM(due_amount) as total_due');

        if ($fromDate) {
            $revenueQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $revenueQuery->whereDate('created_at', '<=', $toDate);
        }
        $revenue = $revenueQuery->first();

        // Revenue by date range (Excluding Cancelled Bookings)
        $revenueByDateQuery = Booking::where('hotel_id', $hotelId)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as revenue')
            ->groupBy('date')
            ->orderBy('date', 'asc');

        if ($fromDate) {
            $revenueByDateQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $revenueByDateQuery->whereDate('created_at', '<=', $toDate);
        }
        $revenueByDate = $revenueByDateQuery->get();

        // Revenue by month (Excluding Cancelled Bookings)
        $revenueByMonthQuery = Booking::where('hotel_id', $hotelId)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(total_amount) as revenue')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc');

        if ($fromDate) {
            $revenueByMonthQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $revenueByMonthQuery->whereDate('created_at', '<=', $toDate);
        }
        $revenueByMonth = $revenueByMonthQuery->limit(12)->get();

        return [
            'total_revenue' => $revenue->total_revenue ?? 0,
            'total_paid' => $revenue->total_paid ?? 0,
            'total_due' => $revenue->total_due ?? 0,
            'revenue_by_date' => $revenueByDate,
            'revenue_by_month' => $revenueByMonth,
        ];
    }

    /**
     * Room Report Data
     */
    private function getRoomReport($hotelId, $fromDate = null, $toDate = null)
    {
        $totalRooms = Room::where('hotel_id', $hotelId)->count();
        
        $occupiedRooms = Room::where('hotel_id', $hotelId)
            ->where('status', 'occupied')
            ->count();
        
        $availableRooms = Room::where('hotel_id', $hotelId)
            ->where('status', 'available')
            ->count();

        // Room-wise revenue with date filtering (Excluding Cancelled Bookings)
        $roomWiseRevenue = Room::where('hotel_id', $hotelId)
            ->with(['bookings' => function ($query) use ($fromDate, $toDate) {
                $query->where('status', '!=', 'cancelled');
                if ($fromDate) {
                    $query->whereDate('created_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $query->whereDate('created_at', '<=', $toDate);
                }
            }])
            ->get()
            ->map(function ($room) {
                $revenue = $room->bookings->sum('total_amount');
                $bookingCount = $room->bookings->count();
                return [
                    'room_id' => $room->id,
                    'room_number' => $room->room_number,
                    'room_type' => $room->room_type,
                    'status' => $room->status,
                    'price' => $room->price,
                    'total_revenue' => $revenue,
                    'booking_count' => $bookingCount,
                ];
            })
            ->sortByDesc('total_revenue')
            ->values();

        return [
            'total_rooms' => $totalRooms,
            'occupied_rooms' => $occupiedRooms,
            'available_rooms' => $availableRooms,
            'occupancy_rate' => $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 2) : 0,
            'room_wise_revenue' => $roomWiseRevenue,
        ];
    }

    /**
     * Service Report Data
     */
    private function getServiceReport($hotelId, $fromDate = null, $toDate = null)
    {
        // Service-wise revenue with date filtering
        $serviceWiseRevenueQuery = BookingService::where('hotel_id', $hotelId)
            ->with('service')
            ->selectRaw('hotel_service_id, SUM(total_price) as revenue, COUNT(*) as usage_count');

        if ($fromDate) {
            $serviceWiseRevenueQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $serviceWiseRevenueQuery->whereDate('created_at', '<=', $toDate);
        }

        $serviceWiseRevenue = $serviceWiseRevenueQuery->groupBy('hotel_service_id')
            ->get()
            ->map(function ($bookingService) {
                return [
                    'service_id' => $bookingService->hotel_service_id,
                    'service_name' => $bookingService->service?->name ?? 'Unknown',
                    'total_revenue' => $bookingService->revenue ?? 0,
                    'usage_count' => $bookingService->usage_count,
                    'avg_price' => $bookingService->usage_count > 0 
                        ? round(($bookingService->revenue ?? 0) / $bookingService->usage_count, 2) 
                        : 0,
                ];
            })
            ->sortByDesc('total_revenue')
            ->values();

        $totalServicesRevenue = $serviceWiseRevenue->sum('total_revenue');

        return [
            'total_services_revenue' => $totalServicesRevenue,
            'service_wise_revenue' => $serviceWiseRevenue,
            'most_used_services' => $serviceWiseRevenue->sortByDesc('usage_count')->take(5)->values(),
        ];
    }

    /**
     * Payment Report Data
     */
    private function getPaymentReport($hotelId, $fromDate = null, $toDate = null)
    {
        // Payments by mode with date filtering
        $paymentByModeQuery = Booking::where('hotel_id', $hotelId)
            ->selectRaw('mode_of_payment, COUNT(*) as count, SUM(paid_amount) as total_paid')
            ->whereNotNull('mode_of_payment');

        if ($fromDate) {
            $paymentByModeQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $paymentByModeQuery->whereDate('created_at', '<=', $toDate);
        }
        $paymentByMode = $paymentByModeQuery->groupBy('mode_of_payment')->get();

        // Pending dues summary with date filtering
        $pendingDuesQuery = Booking::where('hotel_id', $hotelId)
            ->where('due_amount', '>', 0);

        if ($fromDate) {
            $pendingDuesQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $pendingDuesQuery->whereDate('created_at', '<=', $toDate);
        }
        $pendingDues = $pendingDuesQuery->get();

        $pendingDuesSummary = [
            'total_pending_bookings' => $pendingDues->count(),
            'total_pending_amount' => $pendingDues->sum('due_amount'),
            'avg_pending_amount' => $pendingDues->count() > 0 
                ? round($pendingDues->sum('due_amount') / $pendingDues->count(), 2)
                : 0,
        ];

        // Payment breakdown with date filtering
        $paymentBreakdownQuery = Booking::where('hotel_id', $hotelId)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN paid_amount > 0 THEN 1 ELSE 0 END) as paid_bookings, SUM(CASE WHEN due_amount > 0 THEN 1 ELSE 0 END) as due_bookings');

        if ($fromDate) {
            $paymentBreakdownQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $paymentBreakdownQuery->whereDate('created_at', '<=', $toDate);
        }
        $paymentBreakdown = $paymentBreakdownQuery->first();

        return [
            'payment_by_mode' => $paymentByMode,
            'pending_dues_summary' => $pendingDuesSummary,
            'payment_breakdown' => [
                'total_bookings' => $paymentBreakdown->total ?? 0,
                'fully_paid' => $paymentBreakdown->paid_bookings ?? 0,
                'partial_payment' => ($paymentBreakdown->due_bookings ?? 0) > 0 
                    ? ($paymentBreakdown->due_bookings ?? 0)
                    : 0,
            ],
        ];
    }

    /**
     * Generate and download report as CSV
     */
    public function downloadReport(Request $request)
    {
        $hotelId = auth()->user()->hotel_id;
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        // Get all report data with date filtering
        $bookingSummary = $this->getBookingSummary($hotelId, $fromDate, $toDate);
        $revenueReport = $this->getRevenueReport($hotelId, $fromDate, $toDate);
        $roomReport = $this->getRoomReport($hotelId, $fromDate, $toDate);
        $serviceReport = $this->getServiceReport($hotelId, $fromDate, $toDate);
        $paymentReport = $this->getPaymentReport($hotelId, $fromDate, $toDate);

        // Create CSV content
        $csv = $this->generateCSV($bookingSummary, $revenueReport, $roomReport, $serviceReport, $paymentReport, $fromDate, $toDate);

        $filename = 'hotel_report_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Generate CSV content
     */
    private function generateCSV($bookingSummary, $revenueReport, $roomReport, $serviceReport, $paymentReport, $fromDate = null, $toDate = null)
    {
        $csv = "HOTEL MANAGEMENT - COMPREHENSIVE REPORT\n";
        $csv .= "Generated on: " . now()->format('Y-m-d H:i:s') . "\n";
        
        if ($fromDate || $toDate) {
            $csv .= "Report Period: " . ($fromDate ?? 'Start') . " to " . ($toDate ?? 'Today') . "\n";
        }
        
        $csv .= "\n";

        // Booking Summary
        $csv .= "BOOKING SUMMARY\n";
        $csv .= "Total Bookings," . $bookingSummary['total_bookings'] . "\n";
        $csv .= "Confirmed Bookings," . $bookingSummary['confirmed_bookings'] . "\n";
        $csv .= "Cancelled Bookings," . $bookingSummary['cancelled_bookings'] . "\n";
        $csv .= "Pending Bookings," . $bookingSummary['pending_bookings'] . "\n\n";

        // Revenue Summary
        $csv .= "REVENUE SUMMARY\n";
        $csv .= "Total Revenue," . $revenueReport['total_revenue'] . "\n";
        $csv .= "Total Paid," . $revenueReport['total_paid'] . "\n";
        $csv .= "Total Due," . $revenueReport['total_due'] . "\n\n";

        // Room Summary
        $csv .= "ROOM SUMMARY\n";
        $csv .= "Total Rooms," . $roomReport['total_rooms'] . "\n";
        $csv .= "Occupied Rooms," . $roomReport['occupied_rooms'] . "\n";
        $csv .= "Available Rooms," . $roomReport['available_rooms'] . "\n";
        $csv .= "Occupancy Rate (%)," . $roomReport['occupancy_rate'] . "\n\n";

        // Services Summary
        $csv .= "SERVICES SUMMARY\n";
        $csv .= "Total Services Revenue," . $serviceReport['total_services_revenue'] . "\n\n";

        // Payment Summary
        $csv .= "PAYMENT SUMMARY\n";
        $csv .= "Total Pending Amount," . $paymentReport['pending_dues_summary']['total_pending_amount'] . "\n";
        $csv .= "Total Pending Bookings," . $paymentReport['pending_dues_summary']['total_pending_bookings'] . "\n";

        return $csv;
    }
}