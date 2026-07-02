<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsultationFee extends Model
{
    protected $fillable = [
        'amount',
        'currency',
        'is_active',
    ];
}
