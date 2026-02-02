<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\User;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminReviewController extends Controller
{
    /**
     * Get all reviews across all hotels with user and hotel details
     * Super Admin only
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Verify Super Admin access
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized. Super Admin access required.'], 403);
        }

        // Fetch all reviews with relationships
        $reviews = Review::with(['user', 'room.hotel', 'booking'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Format response with required data
        $formattedReviews = $reviews->map(function ($review) {
            return [
                'id' => $review->id,
                'review_id' => $review->id,
                'rating' => $review->rating,
                'title' => $review->title,
                'comment' => $review->comment,
                'status' => $review->status,
                'is_verified' => $review->is_verified,
                    'user_id' => $review->user_id,
                    'user_name' => $review->user->name ?? 'Unknown User',
                    'user_email' => $review->user->email ?? null,
                    'user_is_flagged' => (bool) $review->user->is_flagged,
                    'user' => [
                        'id' => $review->user_id,
                        'name' => $review->user->name ?? 'Unknown User',
                        'email' => $review->user->email ?? null,
                        'is_flagged' => (bool) $review->user->is_flagged,
                    ],
                'hotel_id' => $review->hotel_id,
                'hotel_name' => $review->room->hotel->name ?? 'Unknown Hotel',
                'room_type' => $review->room->room_type ?? 'N/A',
                'created_at' => $review->created_at->toDateTimeString(),
                'created_at_formatted' => $review->created_at->format('M d, Y H:i'),
            ];
        });

        return response()->json([
            'message' => 'All reviews retrieved successfully',
            'reviews' => $formattedReviews,
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'last_page' => $reviews->lastPage(),
            ],
        ]);
    }

    /**
     * Delete a review permanently
     * Super Admin only
     */
    public function destroy($reviewId)
    {
        $user = auth()->user();
        
        // Verify Super Admin access
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized. Super Admin access required.'], 403);
        }

        // Get review
        $review = Review::findOrFail($reviewId);

        // Log deletion action
        \Log::info('Super Admin deleted review', [
            'review_id' => $reviewId,
            'hotel_id' => $review->hotel_id,
            'user_id' => $review->user_id,
            'super_admin_id' => $user->id,
            'reason' => 'Deleted by Super Admin',
        ]);

        try {
            $review->delete();
            return response()->json(['message' => 'Review deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete review', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Flag a user (hide their reviews from public pages)
     * Super Admin only
     */
    public function flagUser($userId)
    {
        $user = auth()->user();
        
        // Verify Super Admin access
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized. Super Admin access required.'], 403);
        }

        // Get user
        $targetUser = User::findOrFail($userId);

        // Check if already flagged
        if ($targetUser->is_flagged) {
            return response()->json(['message' => 'User is already flagged.'], 400);
        }

        try {
            // Flag the user
            $targetUser->update(['is_flagged' => true]);

            // Log action
            \Log::warning('Super Admin flagged user', [
                'user_id' => $userId,
                'user_name' => $targetUser->name,
                'super_admin_id' => $user->id,
                'reason' => 'User flagged by Super Admin',
            ]);

            return response()->json([
                'message' => 'User flagged successfully',
                'user' => [
                    'id' => $targetUser->id,
                    'name' => $targetUser->name,
                    'email' => $targetUser->email,
                    'is_flagged' => (bool) $targetUser->is_flagged,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to flag user', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Unflag a user (show their reviews on public pages again)
     * Super Admin only
     */
    public function unflagUser($userId)
    {
        $user = auth()->user();
        
        // Verify Super Admin access
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized. Super Admin access required.'], 403);
        }

        // Get user
        $targetUser = User::findOrFail($userId);

        // Check if not flagged
        if (!$targetUser->is_flagged) {
            return response()->json(['message' => 'User is not flagged.'], 400);
        }

        try {
            // Unflag the user
            $targetUser->update(['is_flagged' => false]);

            // Log action
            \Log::info('Super Admin unflagged user', [
                'user_id' => $userId,
                'user_name' => $targetUser->name,
                'super_admin_id' => $user->id,
                'reason' => 'User unflagged by Super Admin',
            ]);

            return response()->json([
                'message' => 'User unflagged successfully',
                'user' => [
                    'id' => $targetUser->id,
                    'name' => $targetUser->name,
                    'email' => $targetUser->email,
                    'is_flagged' => (bool) $targetUser->is_flagged,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to unflag user', 'error' => $e->getMessage()], 500);
        }
    }
}
