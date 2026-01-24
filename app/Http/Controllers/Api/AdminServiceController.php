<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelService;
use Illuminate\Http\Request;

class AdminServiceController extends Controller
{
    public function index()
    {
        return HotelService::where('hotel_id', auth()->user()->hotel_id)->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric|min:0',
            'status' => 'required|boolean',
        ]);

        return HotelService::create([
            'hotel_id' => auth()->user()->hotel_id,
            'name' => $request->name,
            'price' => $request->price,
            'status' => $request->status,
        ]);
    }

    public function show(HotelService $service)
    {
        $this->authorizeHotel($service);
        return $service;
    }

    public function update(Request $request, HotelService $service)
    {
        $this->authorizeHotel($service);

        $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric|min:0',
            'status' => 'required|boolean',
        ]);

        $service->update($request->only(['name', 'price', 'status']));
        return $service;
    }

    public function destroy(HotelService $service)
    {
        $this->authorizeHotel($service);
        $service->delete();

        return response()->json(['message' => 'Service deleted']);
    }

    private function authorizeHotel(HotelService $service)
    {
        if ($service->hotel_id !== auth()->user()->hotel_id) {
            abort(403, 'Forbidden');
        }
    }
}
