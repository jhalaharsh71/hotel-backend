<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'booking_id',
        'first_name',
        'last_name',
        'gender',
        'age',
        'id_type',
        'id_number',
        'phone',
        'email',
        'is_primary',
        'status',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_primary' => 'boolean',
        'age' => 'integer',
    ];

    /**
     * Relationship: Guest belongs to a booking
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
