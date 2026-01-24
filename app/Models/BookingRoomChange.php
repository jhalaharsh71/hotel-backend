<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingRoomChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'old_room_id',
        'new_room_id',
        'old_room_price',
        'new_room_price',
        'old_total_amount',
        'new_total_amount',
        'changed_by_user_id',
        'changed_at',
        'change_after_days',
        'old_room_stay_cost',
        'new_room_stay_cost',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function oldRoom()
    {
        return $this->belongsTo(Room::class, 'old_room_id');
    }

    public function newRoom()
    {
        return $this->belongsTo(Room::class, 'new_room_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
