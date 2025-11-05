<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftRequest extends Model
{
    protected $fillable = [
        'user_id', 'store_id', 'shift_date',
        'start_time', 'end_time', 'is_day_off', 'status'
    ];

    // ✅ 日付・日時フィールドを Carbon 化
    protected $casts = [
        'shift_date' => 'date',
        'start_time' => 'datetime',
        'end_time'   => 'datetime',
        'is_day_off' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
