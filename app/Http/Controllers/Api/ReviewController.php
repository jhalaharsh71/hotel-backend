<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /**
     * Create a new review for a booking
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $bookingId = $request->input('booking_id');

        // Validate input
        $validator = Validator::make($request->all(), [
            'booking_id' => 'required|exists:bookings,id',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'comment' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // Get the booking
        $booking = Booking::findOrFail($bookingId);

        // Verify booking belongs to authenticated user
        if ($booking->created_by_user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized. This booking does not belong to you.'], 403);
        }

        // Verify booking status is checkout
        if ($booking->status !== 'checkout') {
            return response()->json(['message' => 'Reviews can only be submitted for completed bookings (status: checkout).'], 400);
        }

        // Verify booking is confirmed
        if (!$booking->confirm_booking) {
            return response()->json(['message' => 'Reviews can only be submitted for confirmed bookings.'], 400);
        }

        // Check if review already exists for this booking
        $existingReview = Review::where('booking_id', $bookingId)->first();
        if ($existingReview) {
            return response()->json(['message' => 'You have already submitted a review for this booking.'], 400);
        }

        // Create review within transaction
        try {
            $review = DB::transaction(function () use ($request, $user, $booking) {
                return Review::create([
                    'booking_id' => $request->input('booking_id'),
                    'hotel_id' => $booking->hotel_id,
                    'room_id' => $booking->room_id,
                    'user_id' => $user->id,
                    'rating' => $request->input('rating'),
                    'title' => $request->input('title'),
                    'comment' => $request->input('comment'),
                    'is_verified' => true, // User stayed at the hotel
                    'status' => 'approved', // Auto-approve
                ]);
            });

            return response()->json([
                'message' => 'Review submitted successfully',
                'review' => $this->formatReview($review),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create review', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get review for a specific booking
     */
    public function getByBooking($bookingId)
    {
        $user = auth()->user();

        // Get the booking first
        $booking = Booking::findOrFail($bookingId);

        // Verify booking belongs to authenticated user
        if ($booking->created_by_user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get review for this booking
        $review = Review::where('booking_id', $bookingId)->first();

        if (!$review) {
            return response()->json(null);
        }

        return response()->json($this->formatReview($review));
    }

    /**
     * Update an existing review
     */
    public function update(Request $request, $reviewId)
    {
        $user = auth()->user();

        // Validate input
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'comment' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // Get review
        $review = Review::findOrFail($reviewId);

        // Verify user owns the review
        if ($review->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized. You can only edit your own reviews.'], 403);
        }

        // Update review
        try {
            $review->update([
                'rating' => $request->input('rating'),
                'title' => $request->input('title'),
                'comment' => $request->input('comment'),
            ]);

            return response()->json([
                'message' => 'Review updated successfully',
                'review' => $this->formatReview($review),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update review', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a review
     */
    public function destroy($reviewId)
    {
        $user = auth()->user();

        // Get review
        $review = Review::findOrFail($reviewId);

        // Verify user owns the review
        if ($review->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized. You can only delete your own reviews.'], 403);
        }

        // Delete review
        try {
            $review->delete();
            return response()->json(['message' => 'Review deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete review', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all approved reviews for a hotel (supports hotel_id query parameter)
     */
    public function getReviewsByHotelId(Request $request)
    {
        $hotelId = $request->query('hotel_id');

        if (!$hotelId) {
            return response()->json(['message' => 'hotel_id query parameter is required'], 400);
        }

        $reviews = Review::where('hotel_id', $hotelId)
            ->where('status', 'approved')
            ->with(['user', 'room'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($reviews->map(fn($review) => $this->formatHotelReview($review)));
    }

    /**
     * Get all approved reviews for a hotel
     */
    public function getHotelReviews($hotelId)
    {
        $reviews = Review::where('hotel_id', $hotelId)
            ->where('status', 'approved')
            ->with(['user', 'room'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($reviews->map(fn($review) => $this->formatHotelReview($review)));
    }

    /**
     * Format review for API response (hides sensitive IDs)
     */
    private function formatReview($review)
    {
        return [
            'id' => $review->id,
            'booking_id' => $review->booking_id,
            'rating' => $review->rating,
            'title' => $review->title,
            'comment' => $review->comment,
            'is_verified' => $review->is_verified,
            'status' => $review->status,
            'room_type' => $review->room->room_type ?? null,
            'created_at' => $review->created_at->toDateTimeString(),
            'updated_at' => $review->updated_at->toDateTimeString(),
        ];
    }

    /**
     * Format review for hotel display (hides sensitive data)
     */
    private function formatHotelReview($review)
    {
        return [
            'id' => $review->id,
            'user_name' => $review->user->name ?? 'Anonymous',
            'room_type' => $review->room->room_type ?? 'Room',
            'rating' => $review->rating,
            'title' => $review->title,
            'comment' => $review->comment,
            'created_at' => $review->created_at->toDateTimeString(),
            'created_at_formatted' => $review->created_at->diffForHumans(),
        ];
    }

    /**
     * Admin: Get all reviews for the admin's hotel
     */
    public function adminGetHotelReviews(Request $request)
    {
        $adminUser = auth()->user();
        
        // Get hotel_id from admin user's profile (admin must have hotel_id assigned)
        $hotelId = $adminUser->hotel_id;
        
        if (!$hotelId) {
            return response()->json(['message' => 'Hotel ID not found for this admin user'], 400);
        }

        // Get all reviews for the hotel (approved and other statuses)
        $reviews = Review::where('hotel_id', $hotelId)
            ->with(['user', 'room'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($reviews->map(fn($review) => $this->formatAdminReview($review)));
    }

    /**
     * Admin: Delete a review
     */
    public function adminDeleteReview($reviewId)
    {
        // Get review
        $review = Review::findOrFail($reviewId);

        // Delete review
        try {
            $review->delete();
            return response()->json(['message' => 'Review deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete review', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Format review for admin display
     */
    private function formatAdminReview($review)
    {
        return [
            'id' => $review->id,
            'user_name' => $review->user->name ?? 'Anonymous',
            'room_type' => $review->room->room_type ?? 'Room',
            'rating' => $review->rating,
            'title' => $review->title,
            'comment' => $review->comment,
            'status' => $review->status,
            'is_verified' => $review->is_verified,
            'created_at' => $review->created_at->toDateTimeString(),
            'created_at_formatted' => $review->created_at->format('M d, Y'),
        ];
    }
}
