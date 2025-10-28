<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    // LINEログイン用
    public function loginWithLine($line_user_id)
    {
        $user = User::where('line_user_id', $line_user_id)->first();
        if (!$user) {
            return redirect('/')->with('error', 'ユーザーが見つかりません');
        }

        auth()->login($user);

        return redirect()->route('shift.calendar', ['user' => $user->id]);
    }

    // カレンダー表示
    public function calendar(User $user)
    {
        return view('shift.calendar', compact('user'));
    }

    // シフト保存
    public function save(Request $request)
    {
        Shift::updateOrCreate(
            ['user_id' => $request->user_id, 'shift_date' => $request->shift_date],
            ['start_time' => $request->start_time, 'end_time' => $request->end_time]
        );

        return response()->json(['success' => true]);
    }
}
