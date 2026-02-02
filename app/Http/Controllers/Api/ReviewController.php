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
     * Centralized list of abusive/profane word patterns
     * Uses regex with + quantifiers to catch repeated letters (e.g., fuuuuuck)
     * Works on normalized text (spaces and symbols already removed)
     * 
     * Includes: Indian abusive words, English profanity, caste-based abuse
     */
    private $abusivePatterns = [
        // Hindi/Hinglish abuse - with + quantifiers for repeated letters
        '/c+h+u+t+i+y+[ae]/i',
        '/m+a+d+a+r+c+h+o+d/i',
        '/b+h+e+n+c+h+o+d/i',
        '/g+a+n+d+u/i',
        '/g+a+n+d+u+g+i+r+i/i',
        '/h+a+r+a+m+i/i',
        '/k+a+m+i+n+a/i',
        '/s+a+a+l+[ae]/i',
        '/l+o+d+a/i',
        '/l+u+n+d/i',
        '/l+o+d+u/i',
        '/l+u+l+l+i/i',
        '/r+a+n+d+[iw]/i',
        '/r+a+n+d+w+a/i',
        '/b+h+a+d+w+a/i',
        '/b+h+a+i+y+a/i',
        '/d+a+l+a+l/i',
        '/g+h+a+t+i+y+a/i',
        '/j+h+a+t+u/i',
        '/k+u+t+t+a/i',
        '/p+a+g+a+l/i',
        '/s+u+a+r/i',
        
        // English profanity - with + quantifiers
        '/f+u+c+k/i',
        '/f+u+c+k+i+n+g/i',
        '/s+h+i+t/i',
        '/b+u+l+l+s+h+i+t/i',
        '/b+i+t+c+h/i',
        '/a+s+s+h+o+l+e/i',
        '/b+a+s+t+a+r+d/i',
        '/c+r+a+p/i',
        '/d+i+c+k/i',
        '/d+a+m+n/i',
        '/h+e+l+l/i',
        '/p+i+s+s/i',
        
        // Caste-based abuse
        '/c+h+a+m+a+r/i',
        '/b+h+a+n+g+i/i',
        '/d+o+m/i',
        '/d+h+o+b+i/i',
        '/n+a+i/i',
    ];

    /**
     * Normalize review text for abuse detection
     * 
     * Process:
     * 1. Convert to lowercase
     * 2. Remove emojis and special Unicode characters
     * 3. Remove common character substitutions (@, #, $, *, etc.)
     * 4. Collapse repeated characters (3+ becomes single)
     *    Example: fuuuuuccckkkk → fuck
     * 5. Remove ALL spaces
     *    Example: f u c k → fuck
     * 6. Remove hyphens and underscores
     * 
     * @param string $text
     * @return string
     */
    private function normalizeText($text)
    {
        // 1. Convert to lowercase
        $text = strtolower($text);
        
        // 2. Remove emojis and special Unicode characters
        $text = preg_replace('/[\x{1F300}-\x{1F9FF}]/u', '', $text);
        
        // 3. Remove common character substitutions (symbol-based obfuscation)
        // f*u*c*k → fuck, m@d@r → madr, etc.
        $text = preg_replace('/[!@#$%^&*~`\-_+=\[\]{}|;:\'",.<>?\\/]/u', '', $text);
        
        // 4. Collapse repeated characters (3+ becomes 1)
        // fuuuuuccckkkk → fuck, bhhhheeenccchhhooodddd → bhenchod
        // This regex matches 3+ consecutive identical characters and replaces with 1
        $text = preg_replace('/(.)\1{2,}/u', '$1', $text);
        
        // 5. Remove ALL spaces (catches variations like f u c k, b h e n c h o d)
        $text = str_replace(' ', '', $text);
        
        // 6. Trim whitespace
        $text = trim($text);
        
        return $text;
    }

    /**
     * Check if text contains abusive/profane language
     * 
     * Process:
     * 1. Normalize the text (lowercase, remove symbols, collapse repeats, remove spaces)
     * 2. Match against regex patterns with + quantifiers
     *    - /f+u+c+k/i catches: fuck, fuuck, fuuuuck, fuCk, FuCk, etc.
     *    - /c+h+u+t+i+y+[ae]/i catches: chutiya, chuutiya, chutiyaa, etc.
     * 3. Return true if any pattern matches
     * 
     * @param string $text
     * @return bool
     */
    private function containsAbusiveLanguage($text)
    {
        if (empty($text)) {
            return false;
        }
        
        // Normalize the text
        // This removes symbols, collapses repeated chars, removes spaces
        $normalizedText = $this->normalizeText($text);
        
        // If normalized text is empty after cleaning, it's safe
        if (empty($normalizedText)) {
            return false;
        }
        
        // Check against each regex pattern
        foreach ($this->abusivePatterns as $pattern) {
            if (preg_match($pattern, $normalizedText)) {
                return true;
            }
        }
        
        return false;
    }

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

        // Check for abusive language in title
        $title = $request->input('title', '');
        if (!empty($title) && $this->containsAbusiveLanguage($title)) {
            return response()->json([
                'message' => 'Your review contains inappropriate or abusive language.',
            ], 422);
        }

        // Check for abusive language in comment
        $comment = $request->input('comment');
        if ($this->containsAbusiveLanguage($comment)) {
            return response()->json([
                'message' => 'Your review contains inappropriate or abusive language.',
            ], 422);
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

        // Check for abusive language in title
        $title = $request->input('title', '');
        if (!empty($title) && $this->containsAbusiveLanguage($title)) {
            return response()->json([
                'message' => 'Your review contains inappropriate or abusive language.',
            ], 422);
        }

        // Check for abusive language in comment
        $comment = $request->input('comment');
        if ($this->containsAbusiveLanguage($comment)) {
            return response()->json([
                'message' => 'Your review contains inappropriate or abusive language.',
            ], 422);
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
     * Re-validates for abusive content before allowing deletion
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

        // Re-validate the stored review text for abusive content
        // If found, log it but still allow deletion
        $isAbusiveTitle = !empty($review->title) && $this->containsAbusiveLanguage($review->title);
        $isAbusiveComment = $this->containsAbusiveLanguage($review->comment);
        $isAbusive = $isAbusiveTitle || $isAbusiveComment;

        // Delete review
        try {
            // If abusive content detected, you can log it here for admin review
            // This allows deletion while keeping records of potentially problematic content
            if ($isAbusive) {
                \Log::warning('Review deleted with abusive content detected', [
                    'review_id' => $reviewId,
                    'hotel_id' => $review->hotel_id,
                    'user_id' => $user->id,
                    'reason' => 'Deleted due to abusive content',
                    'title' => $review->title,
                    'comment' => substr($review->comment, 0, 100), // Log first 100 chars
                ]);
            }

            $review->delete();
            return response()->json(['message' => 'Review deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete review', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all approved reviews for a hotel (supports hotel_id query parameter)
     * EXCLUDES reviews from flagged users (is_flagged = 1)
     */
    public function getReviewsByHotelId(Request $request)
    {
        $hotelId = $request->query('hotel_id');

        if (!$hotelId) {
            return response()->json(['message' => 'hotel_id query parameter is required'], 400);
        }

        // Get reviews from non-flagged users only
        $reviews = Review::where('hotel_id', $hotelId)
            ->where('status', 'approved')
            ->whereHas('user', function ($query) {
                $query->where('is_flagged', false);
            })
            ->with(['user', 'room'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($reviews->map(fn($review) => $this->formatHotelReview($review)));
    }

    /**
     * Get all approved reviews for a hotel
     * EXCLUDES reviews from flagged users (is_flagged = 1)
     */
    public function getHotelReviews($hotelId)
    {
        // Get reviews from non-flagged users only
        $reviews = Review::where('hotel_id', $hotelId)
            ->where('status', 'approved')
            ->whereHas('user', function ($query) {
                $query->where('is_flagged', false);
            })
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
            'admin_reply' => $review->admin_reply,
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
            'admin_reply' => $review->admin_reply,
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
     * Admin: Reply to a review
     */
    public function replyToReview(Request $request, $reviewId)
    {
        $admin = auth()->user();

        // Get review
        $review = Review::findOrFail($reviewId);

        // Verify admin owns the hotel of this review
        if ($review->hotel_id !== $admin->hotel_id) {
            return response()->json(['message' => 'Unauthorized. This review does not belong to your hotel.'], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'reply' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // Update review with admin reply
        try {
            $review->update([
                'admin_reply' => $request->input('reply'),
            ]);

            return response()->json([
                'message' => 'Reply sent successfully',
                'admin_reply' => $review->admin_reply,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send reply', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Admin: Delete reply from a review
     */
    public function deleteReply($reviewId)
    {
        $admin = auth()->user();

        // Get review
        $review = Review::findOrFail($reviewId);

        // Verify admin owns the hotel of this review
        if ($review->hotel_id !== $admin->hotel_id) {
            return response()->json(['message' => 'Unauthorized. This review does not belong to your hotel.'], 403);
        }

        // Delete reply
        try {
            $review->update([
                'admin_reply' => null,
            ]);

            return response()->json(['message' => 'Reply deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete reply', 'error' => $e->getMessage()], 500);
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
            'admin_reply' => $review->admin_reply,
            'status' => $review->status,
            'is_verified' => $review->is_verified,
            'created_at' => $review->created_at->toDateTimeString(),
            'created_at_formatted' => $review->created_at->format('M d, Y'),
        ];
    }
}
