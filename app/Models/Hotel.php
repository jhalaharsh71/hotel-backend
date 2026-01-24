<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Hotel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'address',
        'country',
        'city',
        'state',
        'pincode',
        'contact_no',
        'status',
    ];


    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function services()
    {
        return $this->hasMany(HotelService::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
