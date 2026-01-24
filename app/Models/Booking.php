<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'customer_name',
        'email',
        'phone',
        'check_in_date',
        'check_out_date',
        'room_id',
        'confirm_booking',
        'total_amount',
        'paid_amount',
        'due_amount',
        'mode_of_payment',
        'online_payment_status',
        'created_by_user_id',
        'status',
        'no_of_people',
    ];

    protected $casts = [
        'confirm_booking' => 'boolean',
        'check_in_date' => 'date',
        'check_out_date' => 'date',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function bookingServices()
    {
        return $this->hasMany(BookingService::class);
    }

    public function roomChanges()
    {
        return $this->hasMany(BookingRoomChange::class);
    }

    public function guests()
    {
        return $this->hasMany(Guest::class);
    }
}
