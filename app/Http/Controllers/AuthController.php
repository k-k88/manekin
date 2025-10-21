<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // 🔹 ログインフォームを表示
    public function showLoginForm()
    {
        return view('auth.login');
    }

    // 🔹 ログイン処理
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            // 企業管理者のみログイン可能にしたい場合
            $user = Auth::user();
            if ($user->role !== 'company_admin') {
                Auth::logout();
                return back()->withErrors(['email' => '企業管理者のみログインできます。']);
            }

            return redirect()->route('company.dashboard', ['company' => $user->company_id]);
        }

        return back()->withErrors([
            'email' => 'メールアドレスまたはパスワードが正しくありません。',
        ]);
    }

    // 🔹 ログアウト処理
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
