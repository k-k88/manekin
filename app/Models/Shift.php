<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    /**
     * 一括代入可能な属性
     */
    protected $fillable = [
        'user_id',
        'store_id',
        'shift_date',
        'start_time',
        'end_time',
        'is_day_off',     // 休みかどうか（0/1）
        'is_paid_leave',  // 有休かどうか（0/1）
        'status',         // 承認状態（approved/pendingなど）
    ];

    /**
     * 型キャスト
     */
    protected $casts = [
        'shift_date'     => 'string',
        'start_time'     => 'string',   // datetime ではなく文字列として扱う
        'end_time'       => 'string',
        'is_day_off'     => 'boolean',
        'is_paid_leave'  => 'boolean',
    ];

    /**
     * リレーション: Shift は 1 人のユーザーに属する
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * リレーション: Shift は 1 つの店舗に属する
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
