<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingService extends Model
{
    use HasFactory;

 protected $fillable = [
        'hotel_id',
        'booking_id',
        'hotel_service_id',
        'quantity',
        'unit_price',
        'total_price',
        'paid_amount',
    ];

    // 🔗 Relationships

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function service()
    {
        return $this->belongsTo(HotelService::class, 'hotel_service_id');
    }

    
}
