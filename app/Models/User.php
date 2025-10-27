<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes; // ← 追加！
use Illuminate\Http\Request; 

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes; // ← ここに追加！

    protected $fillable = [
        'name',
        'email',
        'password',
        'line_user_id',
        'phone',
        'role',
        'store_id',
        'company_id',
        'hire_date',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ✅ 勤怠データ（1ユーザー対多勤怠）
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    // ✅ 所属店舗（1ユーザー対1店舗）
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    // ✅ 所属企業（1ユーザー対1企業）
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function createUser(Company $company)
    {
        $stores = $company->stores()->get();
        return view('company.users_create', compact('company', 'stores'));
    }

    public function storeUser(Request $request, Company $company)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'store_id' => 'required|exists:stores,id',
            'hire_date' => 'required|date',
            'password' => 'required|string|min:6',
        ]);

        $validated['password'] = bcrypt($validated['password']);
        $validated['company_id'] = $company->id;
        $validated['role'] = 'employee';

        User::create($validated);

        return redirect()->route('company.users', $company->id)
            ->with('success', '社員を登録しました。');
    }
}
