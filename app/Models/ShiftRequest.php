<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftRequest extends Model
{
    protected $fillable = [
        'user_id', 'store_id', 'shift_date',
        'start_time', 'end_time', 'is_day_off', 'status','is_paid_leave', 
    ];

    protected $casts = [
        
         'shift_date' => 'date',
        'start_time' => 'string',   // ← ★ここを datetime → string
        'end_time'   => 'string',  
        'is_day_off' => 'boolean',
        'is_paid_leave'  => 'boolean',
    ];

    // ✅ 関連付け
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
