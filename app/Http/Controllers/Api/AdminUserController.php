<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    /**
     * Get all users for the current admin's hotel
     * Filters by hotel_id = auth()->user()->hotel_id
     */
    public function index()
    {
        $hotelId = auth()->user()->hotel_id;

        $users = User::where('hotel_id', $hotelId)
            ->where('role', '=', 'user')
            ->select('id', 'name', 'email', 'role', 'hotel_id', 'status', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
            'count' => $users->count(),
        ], 200);
    }

    /**
     * Get a specific user
     */
    public function show($userId)
    {
        $hotelId = auth()->user()->hotel_id;

        $user = User::where('id', $userId)
            ->where('hotel_id', $hotelId)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user,
        ], 200);
    }

    /**
     * Delete a user (only if it belongs to current admin's hotel)
     */
    public function destroy($userId)
    {
        $hotelId = auth()->user()->hotel_id;

        $user = User::where('id', $userId)
            ->where('hotel_id', $hotelId)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ], 200);
    }

    /**
     * Update a user (only if it belongs to current admin's hotel)
     */
    public function update(Request $request, $userId)
    {
        $hotelId = auth()->user()->hotel_id;

        $user = User::where('id', $userId)
            ->where('hotel_id', $hotelId)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $userId,
            'role' => 'sometimes|in:admin,staff',
            'status' => 'sometimes|boolean|in:0,1',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user,
        ], 200);
    }
}
