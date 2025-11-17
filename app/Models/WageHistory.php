<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class WageHistory extends Model
{
    use HasFactory;

    // ✅ テーブル名を明示（これが重要！）
    protected $table = 'wage_histories';

    protected $fillable = [
        'user_id',
        'hourly_wage',
        'effective_from',
        'end_date',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'end_date'       => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted()
    {
        static::creating(function ($record) {
            self::where('user_id', $record->user_id)
                ->whereNull('end_date')
                ->update([
                    'end_date' => Carbon::parse($record->effective_from)->subDay(),
                ]);
        });
    }

    public static function getWageForDate($userId, $targetDate)
    {
        return self::where('user_id', $userId)
            ->where('effective_from', '<=', $targetDate)
            ->where(function ($q) use ($targetDate) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $targetDate);
            })
            ->orderByDesc('effective_from')
            ->first();
    }
}
