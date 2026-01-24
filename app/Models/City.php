<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $table = 'cities';

    protected $fillable = [
        'name',
        'state_id',
    ];

    public $timestamps = false;

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }
}
