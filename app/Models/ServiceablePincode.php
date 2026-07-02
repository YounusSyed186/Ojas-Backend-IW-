<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceablePincode extends Model
{
    protected $fillable = [
        'pincode',
        'label',
        'is_active',
    ];
}
