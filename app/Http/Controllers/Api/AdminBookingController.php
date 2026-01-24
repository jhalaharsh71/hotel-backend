<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Models\BookingRoomChange;
use App\Mail\BookingConfirmationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;



class AdminBookingController extends Controller
{
   
    private function isRoomAvailable($roomId, $checkIn, $checkOut)
    {
        return !Booking::where('room_id', $roomId)
            ->where('confirm_booking', true)
            ->where('status', 'check-in')
            ->where('check_in_date', '<', $checkOut)
            ->where('check_out_date', '>', $checkIn)
            ->exists();
    }

    public function index()
    {
        return Booking::with('room')
            ->where('hotel_id', auth()->user()->hotel_id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function availableRooms(Request $request)
    {
        $request->validate([
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'no_of_people' => 'required|integer|min:1',
        ]);

        return Room::where('hotel_id', auth()->user()->hotel_id)
            // Filter by people capacity: min_people <= no_of_people <= max_people
            ->where('min_people', '<=', $request->no_of_people)
            ->where('max_people', '>=', $request->no_of_people)
            ->whereNotIn('id', function ($q) use ($request) {
                $q->select('room_id')
                ->from('bookings')
                ->where('confirm_booking', true)
                ->whereIn('status', ['active', 'check-in'])
                ->where('check_in_date', '<', $request->check_out)
                ->where('check_out_date', '>', $request->check_in);
            })

            ->get();
    }

public function availableRoomsForRoomChange(Request $request)
{
    $request->validate([
        'check_in' => 'required|date|date_format:Y-m-d',
        'check_out' => 'required|date|date_format:Y-m-d|after:check_in',
        'current_room_id' => 'nullable|integer',
        'no_of_people' => 'required|integer|min:1',
    ]);

    return Room::where('hotel_id', auth()->user()->hotel_id)

        // ❌ Exclude current room
        ->when($request->current_room_id, function ($query) use ($request) {
            $query->where('id', '!=', $request->current_room_id);
        })

        // ❌ Exclude rooms with overlapping confirmed bookings (active or check-in only)
        // Rooms with checkout or cancelled bookings ARE available
        ->whereDoesntHave('bookings', function ($q) use ($request) {
            $q->where('confirm_booking', true)
              ->whereIn('status', ['active', 'check-in'])
              ->where('check_in_date', '<', $request->check_out)
              ->where('check_out_date', '>', $request->check_in);
        })

        // ✅ Capacity condition: min_people <= no_of_people <= max_people
        ->where('min_people', '<=', $request->no_of_people)
        ->where('max_people', '>=', $request->no_of_people)

        ->get();
}


    public function store(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'no_of_people' => 'required|integer|min:1',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
            'room_id' => 'required|exists:rooms,id',
            'paid_amount' => 'required|numeric|min:0',
            'mode_of_payment' => 'required|in:Cash,Card,UPI',
            // Guest array validation (required to match no_of_people)
            'guests' => 'required|array',
            'guests.*.first_name' => 'required|string|max:255',
            'guests.*.last_name' => 'required|string|max:255',
            'guests.*.gender' => 'required|string|max:50',
            'guests.*.age' => 'required|integer|min:0|max:150',
            'guests.*.phone' => 'required|string|max:20',
            'guests.*.email' => 'required|email|max:255',
        ]);

        // ===== CONDITION 1: CHECK-OUT DATE VALIDATION =====
        // Ensure check-out date is not before check-in date
        $checkInDate = new \DateTime($request->check_in_date);
        $checkOutDate = new \DateTime($request->check_out_date);
        if ($checkOutDate < $checkInDate) {
            return response()->json([
                'message' => 'Check-out date cannot be earlier than check-in date.'
            ], 422);
        }

        // ===== CONDITION 2: CHECK-IN DATE LIMIT =====
        // Check-in date cannot be more than 2 days before today
        $today = new \DateTime('today');
        $twoDaysAgo = (new \DateTime('today'))->modify('-2 days');
        if ($checkInDate < $twoDaysAgo) {
            return response()->json([
                'message' => 'Check-in date cannot be more than 2 days earlier than today.'
            ], 422);
        }

        if (!$this->isRoomAvailable(
            $request->room_id,
            $request->check_in_date,
            $request->check_out_date
        )) {
            return response()->json([
                'message' => 'Room already booked for selected dates'
            ], 409);
        }

        $room = Room::findOrFail($request->room_id);

        // ===== PRICE CALCULATION FIX =====
        // Calculate the number of days between check-in and check-out
        $checkInDate = new \DateTime($request->check_in_date);
        $checkOutDate = new \DateTime($request->check_out_date);
        $interval = $checkInDate->diff($checkOutDate);
        $numberOfDays = $interval->days;
        
        // Ensure minimum 1 day (in case of edge case)
        if ($numberOfDays < 1) {
            $numberOfDays = 1;
        }
        
        // Calculate total room cost for entire stay duration
        $totalRoomCost = $numberOfDays * $room->price;
        $dueAmount = $totalRoomCost - $request->paid_amount;
        
        // Ensure due_amount is never negative
        if ($dueAmount < 0) {
            $dueAmount = 0;
        }
        // ===== END PRICE CALCULATION FIX =====

        // ===== VALIDATE GUESTS ARRAY =====
        // Guests count must match no_of_people
        $guests = $request->input('guests', []);
        if (count($guests) !== (int) $request->no_of_people) {
            return response()->json([
                'message' => 'Number of guests must match no_of_people',
            ], 422);
        }

        // ===== CREATE BOOKING + GUESTS WITH TRANSACTION =====
        DB::beginTransaction();
        try {
            // Create booking
            $booking = Booking::create([
                'hotel_id' => auth()->user()->hotel_id,
                'customer_name' => $request->customer_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'no_of_people' => $request->no_of_people,
                'check_in_date' => $request->check_in_date,
                'check_out_date' => $request->check_out_date,
                'room_id' => $room->id,
                'confirm_booking' => true,
                'total_amount' => $totalRoomCost,
                'paid_amount' => $request->paid_amount,
                'due_amount' => $dueAmount,
                'mode_of_payment' => $request->mode_of_payment,
                'created_by_user_id' => auth()->id(),
                'status' => 'active',
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

            // ===== SEND BOOKING CONFIRMATION EMAIL =====
            // Send confirmation email to customer (with error handling - do not block booking creation)
            if ($booking->email) {
                try {
                    Mail::to($booking->email)->send(new BookingConfirmationMail($booking));
                } catch (\Exception $e) {
                    // Log email error but do not fail the booking creation
                    \Log::error('Failed to send booking confirmation email for booking ID: ' . $booking->id, [
                        'error' => $e->getMessage(),
                        'customer_email' => $booking->email,
                    ]);
                }
            }
            // ===== END BOOKING CONFIRMATION EMAIL =====

            return $booking;

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error creating booking or guests',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
public function update(Request $request, Booking $booking)
{
    if ($booking->hotel_id !== auth()->user()->hotel_id) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    // ===== PREVENT EDITS WHEN CHECKED OUT =====
    if ($booking->status === 'checkout') {
        return response()->json([
            'message' => 'Cannot edit a checked-out booking. Guest details, stay dates, and room cannot be changed.'
        ], 422);
    }

    // STAY EXTENSION/REDUCTION UPDATE - Only check-out date, no room change
    if ($request->has('check_out_date')) {

        $request->validate([
            'check_out_date' => 'required|date|after_or_equal:check_in_date',
        ]);

        // ===== CONDITION 1: CHECK-OUT DATE VALIDATION =====
        // Ensure check_out_date is not before check_in_date
        $checkInDate = new \DateTime($booking->check_in_date);
        $newCheckoutDate = new \DateTime($request->check_out_date);
        
        if ($newCheckoutDate < $checkInDate) {
            return response()->json(['message' => 'Check-out date cannot be earlier than check-in date.'], 422);
        }

        $oldCheckoutDate = new \DateTime($booking->check_out_date);
        $interval = $oldCheckoutDate->diff($newCheckoutDate);
        
        // Positive days = extension, negative days = reduction
        $daysDifference = $interval->days;
        if ($newCheckoutDate < $oldCheckoutDate) {
            $daysDifference = -$daysDifference;
        }

        // Calculate additional/reduction amount
        $amountChange = 0;
        if ($daysDifference != 0) {
            $roomPrice = $booking->room->price;
            $amountChange = $daysDifference * $roomPrice;
        }

        // Calculate new totals
        $newTotalAmount = $booking->total_amount + $amountChange;
        
        // Ensure total_amount never goes below paid_amount
        if ($newTotalAmount < $booking->paid_amount) {
            $newTotalAmount = $booking->paid_amount;
        }

        // Calculate new due amount
        $newDueAmount = $newTotalAmount - $booking->paid_amount;
        if ($newDueAmount < 0) {
            $newDueAmount = 0;
        }

        // Update booking
        $booking->update([
            'check_out_date' => $request->check_out_date,
            'total_amount' => $newTotalAmount,
            'due_amount' => $newDueAmount,
        ]);

        return $booking;
    }

    // GUEST + BILLING UPDATE (without room change)
    $request->validate([
        'customer_name' => 'required|string',
        'email' => 'nullable|email',
        'phone' => 'nullable|string',
        'no_of_people' => 'nullable|integer|min:1',
        'mode_of_payment' => 'required|in:Cash,Card,UPI',
    ]);

    $booking->update([
        'customer_name' => $request->customer_name,
        'email' => $request->email,
        'phone' => $request->phone,
        'no_of_people' => $request->no_of_people ?? $booking->no_of_people,
        'mode_of_payment' => $request->mode_of_payment,
        'due_amount' => $booking->total_amount - $booking->paid_amount,
    ]);

    return $booking;
}


    public function destroy(Booking $booking)
    {
        if ($booking->hotel_id !== auth()->user()->hotel_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $booking->delete();
        return response()->json(['message' => 'Booking deleted']);
    }

    public function show(Booking $booking)
    {
        if ($booking->hotel_id !== auth()->user()->hotel_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(
            $booking->load(['room', 'bookingServices.service', 'guests'])
        );
    }

public function addPayment(Request $request, Booking $booking)
{
    if ($booking->hotel_id !== auth()->user()->hotel_id) {
        abort(403);
    }

    $request->validate([
        'amount' => 'required|numeric|min:0.01',
        'mode_of_payment' => 'required|in:Cash,Card,UPI',
    ]);

    $booking->paid_amount += $request->amount;
    $booking->due_amount  = round(
        $booking->total_amount - $booking->paid_amount,
        2
    );
    $booking->mode_of_payment = $request->mode_of_payment;

    $booking->save();

    return $booking;
}

public function changeRoom(Request $request, Booking $booking)
{
    // Authorization check
    if ($booking->hotel_id !== auth()->user()->hotel_id) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    // ===== PREVENT ROOM CHANGE WHEN CHECKED OUT =====
    if ($booking->status === 'checkout') {
        return response()->json(['message' => 'Cannot change room for a checked-out booking.'], 422);
    }

    // Booking status validation
    if ( $booking->status === 'cancelled') {
        return response()->json(['message' => 'Booking is not active'], 422);
    }

    // Validate request
    $request->validate([
        'new_room_id' => 'required|exists:rooms,id',
        'change_after_days' => 'nullable|integer|min:0',
    ]);

    $newRoomId = $request->new_room_id;
    $oldRoom = $booking->room;
    $newRoom = Room::findOrFail($newRoomId);

    // Check if new room belongs to same hotel
    if ($newRoom->hotel_id !== $booking->hotel_id) {
        return response()->json(['message' => 'Room does not belong to your hotel'], 422);
    }

    // Check if trying to change to same room
    if ($newRoom->id === $booking->room_id) {
        return response()->json(['message' => 'New room is same as current room'], 422);
    }

    // Check availability of new room for the booking date range
    if (!$this->isRoomAvailable($newRoom->id, $booking->check_in_date, $booking->check_out_date)) {
        return response()->json(['message' => 'Selected room is not available for the booking dates'], 409);
    }

    // Calculate total stay days
    $checkIn = new \DateTime($booking->check_in_date);
    $checkOut = new \DateTime($booking->check_out_date);
    $interval = $checkIn->diff($checkOut);
    $totalDays = $interval->days;

    // Validate change_after_days for multi-day bookings
    $changeAfterDays = $request->change_after_days;
    if ($totalDays > 1) {
        if ($changeAfterDays === null) {
            return response()->json(['message' => 'Change after days is required for multi-day bookings'], 422);
        }
        if ($changeAfterDays < 0 || $changeAfterDays >= $totalDays) {
            return response()->json(['message' => 'Change after days must be between 0 and ' . ($totalDays - 1)], 422);
        }
    }

    // Use transaction to ensure data consistency
    return DB::transaction(function () use ($booking, $oldRoom, $newRoom, $changeAfterDays, $totalDays) {
        $oldRoomPrice = $oldRoom->price;
        $newRoomPrice = $newRoom->price;
        $oldTotalAmount = $booking->total_amount;

        // Calculate new total amount based on partial stay
        $servicesTotal = $booking->bookingServices()->sum('total_price');
        
        if ($totalDays > 1 && $changeAfterDays !== null) {
            // Partial stay pricing - allows X=0 for same-day change
            $oldRoomDays = $changeAfterDays;
            $newRoomDays = $totalDays - $changeAfterDays;
            
            $oldRoomStayCost = $oldRoomDays * $oldRoomPrice;
            $newRoomStayCost = $newRoomDays * $newRoomPrice;
            
            $newTotalAmount = $oldRoomStayCost + $newRoomStayCost + $servicesTotal;
        } else {
            // Single day or full stay replacement
            $newTotalAmount = $newRoomPrice + $servicesTotal;
            $oldRoomStayCost = null;
            $newRoomStayCost = null;
        }

        // Store room change history
        BookingRoomChange::create([
            'booking_id' => $booking->id,
            'old_room_id' => $oldRoom->id,
            'new_room_id' => $newRoom->id,
            'old_room_price' => $oldRoomPrice,
            'new_room_price' => $newRoomPrice,
            'old_total_amount' => $oldTotalAmount,
            'new_total_amount' => $newTotalAmount,
            'changed_by_user_id' => auth()->id(),
            'changed_at' => now(),
            'change_after_days' => $changeAfterDays,
            'old_room_stay_cost' => $oldRoomStayCost,
            'new_room_stay_cost' => $newRoomStayCost,
        ]);

        // Update booking with new room and recalculated amounts
        $booking->update([
            'room_id' => $newRoom->id,
            'total_amount' => $newTotalAmount,
            'due_amount' => $newTotalAmount - $booking->paid_amount,
        ]);

        return response()->json([
            'message' => 'Room changed successfully',
            'booking' => $booking->load(['room', 'bookingServices.service', 'roomChanges']),
        ]);
    });
}
  /* ===== TASK 1: PREVENT CANCEL AFTER CHECKOUT ===== */
  public function cancelBooking(Booking $booking)
    {
        if ($booking->hotel_id !== auth()->user()->hotel_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($booking->status === 'cancelled') {
            return response()->json(['message' => 'Booking is already cancelled'], 422);
        }

        if ($booking->status === 'checkout') {
            return response()->json(['message' => 'Cannot cancel a checked-out booking.'], 422);
        }

        $booking->status = 'cancelled';
        $booking->save();

        return response()->json(['message' => 'Booking cancelled successfully', 'booking' => $booking]);
    }

public function guests()
{
    $hotelId = auth()->user()->hotel_id;
    $today = Carbon::today(); // server date

    $guests = Booking::where('hotel_id', $hotelId)
        ->where('status', 'check-in')
        ->where('confirm_booking', true)

        // ✅ INCLUDE CHECK-OUT DAY
        ->whereDate('check_in_date', '<=', $today)
        ->whereDate('check_out_date', '>=', $today)

        ->with(['room', 'bookingServices.service'])
        ->orderBy('check_in_date', 'desc')
        ->get()
        ->map(function ($booking) {
            return [
                'id' => $booking->id,
                'customer_name' => $booking->customer_name,
                'email' => $booking->email,
                'phone' => $booking->phone,

                'room_number' => optional($booking->room)->room_number,
                'room_type' => optional($booking->room)->room_type,

                'check_in_date' => $booking->check_in_date,
                'check_out_date' => $booking->check_out_date,

                'total_amount' => $booking->total_amount,
                'paid_amount' => $booking->paid_amount,
                'due_amount' => $booking->due_amount,
                'mode_of_payment' => $booking->mode_of_payment,

                'status' => $booking->status,
                'confirm_booking' => $booking->confirm_booking,

                'services' => $booking->bookingServices->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->service->name ?? 'Unknown',
                        'price' => $service->price,
                        'quantity' => $service->quantity,
                        'total_price' => $service->total_price,
                    ];
                }),
            ];
        });

    return response()->json($guests);
}

    public function confirmBooking(Request $request, Booking $booking)
    {
        if ($booking->hotel_id !== auth()->user()->hotel_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($booking->confirm_booking) {
            return response()->json(['message' => 'Booking is already confirmed'], 422);
        }
        $roomOccupied = Booking::where('room_id', $booking->room_id)
    ->where('confirm_booking', true)
    ->whereIn('status', ['active', 'check-in'])
    ->where('id', '!=', $booking->id) // ignore current booking
    ->where(function ($q) use ($booking) {
        $q->where('check_in_date', '<', $booking->check_out_date)
          ->where('check_out_date', '>', $booking->check_in_date);
    })
    ->exists();

if ($roomOccupied) {
    return response()->json([
        'message' => 'Room is already occupied for the selected dates. Change the room before confirming the booking.'
    ], 409);
}


        $status = 'active';

        $booking->confirm_booking = true;
        $booking->status = $status;
        $booking->save();


        if ($booking->email) {
            try {
                Mail::to($booking->email)->send(new BookingConfirmationMail($booking));
            } catch (\Exception $e) {
                \Log::error('Failed to send booking confirmation email for booking ID: ' . $booking->id, [
                    'error' => $e->getMessage(),
                    'customer_email' => $booking->email,
                ]);
            }
        }

        return response()->json([
            'message' => 'Booking confirmed successfully',
            'booking' => $booking->load(['room', 'bookingServices.service'])
        ]);
    }
    // ===== END TASK 3 =====

    // ===== TASK 4: CHECKOUT BOOKING =====
    public function checkoutBooking(Request $request, Booking $booking)
    {
        if ($booking->hotel_id !== auth()->user()->hotel_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // CASE 1: Check if booking is confirmed
        if (!$booking->confirm_booking) {
            return response()->json([
                'message' => 'Cannot checkout unconfirmed bookings. Please confirm the booking first.'
            ], 422);
        }

        // CASE 2: Check if booking is not already cancelled or checked out
        if ($booking->status === 'cancelled') {
            return response()->json([
                'message' => 'Cannot checkout a cancelled booking.'
            ], 422);
        }

        if ($booking->status === 'checkout') {
            return response()->json([
                'message' => 'Booking is already checked out.'
            ], 422);
        }

        // CASE 3: Check if checkout is attempted before checkout date
        $today = new \DateTime('today');
        $checkoutDate = new \DateTime($booking->check_out_date);
        
        if ($today < $checkoutDate) {
            return response()->json([
                'message' => 'To checkout before the checkout date, please update the checkout date first.'
            ], 422);
        }

        // CASE 4: Check if there is pending payment
        if ($booking->due_amount > 0) {
            return response()->json([
                'message' => 'Please collect the pending payment of ₹' . number_format($booking->due_amount, 2) . ' before checkout.'
            ], 422);
        }

        // CASE 5: All conditions met - proceed with checkout
        $booking->status = 'checkout';
        $booking->save();

        // ===== TASK 2: SEND CHECKOUT EMAIL =====
        // Send checkout confirmation email to customer
        if ($booking->email) {
            try {
                Mail::to($booking->email)->send(new \App\Mail\CheckoutCompletedMail($booking));
            } catch (\Exception $e) {
                // Log email error but do not fail the checkout
                \Log::error('Failed to send checkout email for booking ID: ' . $booking->id, [
                    'error' => $e->getMessage(),
                    'customer_email' => $booking->email,
                ]);
            }
        }

        return response()->json([
            'message' => 'Checkout completed successfully',
            'booking' => $booking->load(['room', 'bookingServices.service'])
        ]);
    }
    // ===== END TASK 4 =====

    // ===== CHECK-IN BOOKING =====
    public function checkInBooking(Request $request, Booking $booking)
    {
        if ($booking->hotel_id !== auth()->user()->hotel_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Validate: Booking must be confirmed
        if (!$booking->confirm_booking) {
            return response()->json([
                'message' => 'Cannot check-in unconfirmed bookings. Please confirm the booking first.'
            ], 422);
        }

        // Validate: Booking cannot be cancelled
        if ($booking->status === 'cancelled') {
            return response()->json([
                'message' => 'Cannot check-in a cancelled booking.'
            ], 422);
        }

        // Validate: Booking cannot be already checked out
        if ($booking->status === 'checkout') {
            return response()->json([
                'message' => 'Cannot check-in a checked-out booking.'
            ], 422);
        }

        // Validate: Must be on or after check-in date
        $today = new \DateTime('today');
        $checkInDate = new \DateTime($booking->check_in_date);
        
        if ($today < $checkInDate) {
            return response()->json([
                'message' => 'Guest cannot check-in before the scheduled check-in date.'
            ], 422);
        }

        // All conditions met - proceed with check-in
        $booking->status = 'check-in';
        $booking->save();

        return response()->json([
            'message' => 'Guest checked in successfully',
            'booking' => $booking->load(['room', 'bookingServices.service'])
        ]);
    }
    // ===== END CHECK-IN =====

}