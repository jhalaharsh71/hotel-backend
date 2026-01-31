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
        'about_hotel',
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

    public function roomFeatures()
    {
        return $this->hasMany(RoomFeature::class)->where('is_active', true);
    }

    public function facilities()
    {
        return $this->hasMany(HotelFacility::class)->where('is_active', true);
    }

    public function galleries()
    {
        return $this->hasMany(HotelGallery::class)->where('is_active', true)->orderBy('sort_order');
    }

    public function bannerImage()
    {
        return $this->hasOne(HotelGallery::class)->where('is_banner_image', true)->where('is_active', true);
    }
}
