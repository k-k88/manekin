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

            $user = Auth::user();

            // ✅ super_admin または company_admin 以外は弾く
            if (!in_array($user->role, ['company_admin', 'super_admin'])) {
                Auth::logout();
                return back()->withErrors(['email' => '管理者のみログインできます。']);
            }

            // ✅ super_admin の場合は admin ダッシュボードへ
            if ($user->role === 'super_admin') {
                return redirect()->route('admin.dashboard');
            }

            // ✅ company_admin の場合は会社ダッシュボードへ
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
