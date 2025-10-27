<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    // 👇 単数形テーブル名を明示
    protected $table = 'payroll';

    protected $fillable = [
        'user_id',
        'hourly_wage',
        'total_hours',
        'total_pay',
        'month',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendance()
{
    return $this->belongsTo(Attendance::class);
}

}
