<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\HotelService;
use Illuminate\Http\Request;

class AdminBookingServiceController extends Controller
{
    public function index(Booking $booking)
    {
        $this->authorizeBooking($booking);
        return $booking->bookingServices()->with('service')->get();
    }

    public function store(Request $request, Booking $booking)
    {
        $this->authorizeBooking($booking);

        // ===== TASK 1: SERVICE ADD RESTRICTIONS =====
        // Block adding services if booking is not confirmed, cancelled, or checked out
        if (!$booking->confirm_booking) {
            return response()->json([
                'message' => 'Cannot add services to unconfirmed bookings. Please confirm the booking first.'
            ], 422);
        }

        if ($booking->status === 'cancelled') {
            return response()->json([
                'message' => 'Cannot add services to cancelled bookings.'
            ], 422);
        }

        if ($booking->status === 'checkout') {
            return response()->json([
                'message' => 'Cannot add services to checked-out bookings.'
            ], 422);
        }
        // ===== END SERVICE ADD RESTRICTIONS =====

        $request->validate([
            'hotel_service_id' => 'required|exists:hotel_services,id',
            'quantity' => 'required|integer|min:1',
            'paid_amount' => 'nullable|numeric|min:0',
        ]);

        $service = HotelService::where('id', $request->hotel_service_id)
            ->where('hotel_id', auth()->user()->hotel_id)
            ->firstOrFail();

        $paid = $request->paid_amount ?? 0;
        $total = $service->price * $request->quantity;

        $bookingService = BookingService::create([
            'hotel_id' => auth()->user()->hotel_id,
            'booking_id' => $booking->id,
            'hotel_service_id' => $service->id,
            'quantity' => $request->quantity,
            'unit_price' => $service->price,
            'total_price' => $total,
            'paid_amount' => $paid,
        ]);

        // ✅ INCREMENT booking values (NOT overwrite)
        $booking->increment('total_amount', $total);
        $booking->increment('paid_amount', $paid);
        $booking->due_amount = $booking->total_amount - $booking->paid_amount;
        $booking->save();

        // ===== TASK 3: SEND SERVICE ADDED EMAIL =====
        // Send email notification to customer when service is added
        if ($booking->email) {
            try {
                \Mail::to($booking->email)->send(new \App\Mail\ServiceAddedMail($booking, $bookingService));
            } catch (\Exception $e) {
                // Log email error but do not fail the service addition
                \Log::error('Failed to send service added email for booking ID: ' . $booking->id, [
                    'error' => $e->getMessage(),
                    'customer_email' => $booking->email,
                    'service_id' => $service->id,
                ]);
            }
        }

        return $bookingService->load('service');
    }

    public function update(Request $request, Booking $booking, BookingService $bookingService)
    {
        $this->authorizeBooking($booking);

        $request->validate([
            'quantity' => 'required|integer|min:1',
            'paid_amount' => 'nullable|numeric|min:0',
        ]);

        // 🔥 OLD VALUES
        $oldTotal = $bookingService->total_price;
        $oldPaid  = $bookingService->paid_amount;

        // 🔥 NEW VALUES
        $newTotal = $bookingService->unit_price * $request->quantity;
        $newPaid  = $request->paid_amount ?? $oldPaid;

        $bookingService->update([
            'quantity' => $request->quantity,
            'total_price' => $newTotal,
            'paid_amount' => $newPaid,
        ]);

        // 🔥 APPLY DIFFERENCE
        $booking->total_amount += ($newTotal - $oldTotal);
        $booking->paid_amount  += ($newPaid - $oldPaid);
        $booking->due_amount    = $booking->total_amount - $booking->paid_amount;
        $booking->save();

        return $bookingService->load('service');
    }

    public function destroy(Booking $booking, BookingService $bookingService)
    {
        $this->authorizeBooking($booking);

        // 🔥 SUBTRACT values
        $booking->decrement('total_amount', $bookingService->total_price);
        $booking->decrement('paid_amount', $bookingService->paid_amount);
        $booking->due_amount = $booking->total_amount - $booking->paid_amount;
        $booking->save();

        $bookingService->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function authorizeBooking(Booking $booking)
    {
        if ($booking->hotel_id !== auth()->user()->hotel_id) {
            abort(403);
        }
    }

    private function recalculateBooking(Booking $booking)
{
    $servicesTotal = $booking->bookingServices()->sum('total_price');
    $servicesPaid  = $booking->bookingServices()->sum('paid_amount');

    $roomPrice = $booking->room->price;

    $total = $roomPrice + $servicesTotal;
    $paid  = $servicesPaid;
    $due   = $total - $paid;

    $booking->update([
        'total_amount' => round($total, 2),
        'paid_amount'  => round($paid, 2),
        'due_amount'   => round($due, 2),
    ]);
}

}
