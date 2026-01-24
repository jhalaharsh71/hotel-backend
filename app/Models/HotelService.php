<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HotelService extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'name',
        'price',
        'status',
    ];

    // 🔗 Relationships

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function bookingServices()
    {
        return $this->hasMany(BookingService::class);
    }
}
