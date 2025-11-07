<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftRequest extends Model
{
    protected $fillable = [
        'user_id', 'store_id', 'shift_date',
        'start_time', 'end_time', 'is_day_off', 'status'
    ];

    protected $casts = [
        'shift_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time'   => 'datetime:H:i',
        'is_day_off' => 'boolean',
    ];

    // ✅ 関連付け
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
