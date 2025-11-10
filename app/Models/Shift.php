<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id',
        'shift_date',
        'start_time',
        'end_time',
        'is_day_off',
        'status', // ← ← ← 必須！！！
    ];

    protected $casts = [
       'shift_date' => 'string',
       'start_time' => 'string',   // ← datetime をやめて文字列にする！
        'end_time'   => 'string', 

        'is_day_off' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
