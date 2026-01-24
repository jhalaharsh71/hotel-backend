<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminRoomController extends Controller
{
    public function index()
    {
        $hotelId = auth()->user()->hotel_id;

        $rooms = Room::where('hotel_id', $hotelId)->get();

        return response()->json($rooms);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'room_number' => 'required|string|unique:rooms,room_number,NULL,id,hotel_id,' . auth()->user()->hotel_id,
            'room_type'   => 'required|string',
            'price'       => 'required|numeric|min:0',
            'status'      => 'required|boolean', // ✅ FIXED
            'min_people'  => 'required|integer|min:1', // Minimum occupancy
            'max_people'  => 'required|integer|gte:min_people', // Maximum occupancy, must be >= min_people
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $room = Room::create([
            'hotel_id'    => auth()->user()->hotel_id,
            'room_number' => $request->room_number,
            'room_type'   => $request->room_type,
            'price'       => $request->price,
            'status'      => (int) $request->status, // ✅ FORCE INT
            'min_people'  => $request->min_people,
            'max_people'  => $request->max_people,
        ]);

        return response()->json($room, 201);
    }

    public function update(Request $request, Room $room)
    {
        if ($room->hotel_id !== auth()->user()->hotel_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'room_number' => 'required|string|unique:rooms,room_number,' . $room->id . ',id,hotel_id,' . auth()->user()->hotel_id,
            'room_type'   => 'required|string',
            'price'       => 'required|numeric|min:0',
            'status'      => 'required|boolean', // ✅ FIXED
            'min_people'  => 'required|integer|min:1', // Minimum occupancy
            'max_people'  => 'required|integer|gte:min_people', // Maximum occupancy, must be >= min_people
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $room->update([
            'room_number' => $request->room_number,
            'room_type'   => $request->room_type,
            'price'       => $request->price,
            'status'      => (int) $request->status, // ✅ FIXED
            'min_people'  => $request->min_people,
            'max_people'  => $request->max_people,
        ]);

        return response()->json($room);
    }

    public function destroy(Room $room)
    {
        if ($room->hotel_id !== auth()->user()->hotel_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $room->delete();

        return response()->json(['message' => 'Room deleted']);
    }
}
