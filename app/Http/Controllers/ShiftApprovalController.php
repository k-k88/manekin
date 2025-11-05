<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\ShiftRequest;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ShiftApprovalController extends Controller
{
    // 承認一覧（会社・月・社員で絞り込める）
    public function index(Company $company, Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $userId = $request->input('user_id');

        $q = ShiftRequest::whereHas('user', fn($q) => $q->where('company_id', $company->id))
            ->where('status', 'pending')
            ->where('shift_date', 'like', $month.'%')
            ->with('user')
            ->orderBy('shift_date');

        if ($userId) $q->where('user_id', $userId);

        $requests = $q->get();
        $users = $company->users()->get();

      return view('company.shift.requests', compact('company','requests','users','month','userId'));

    }

    // 単体承認
    public function approve(Company $company, ShiftRequest $requestModel)
    {
        // 会社整合性チェック
        if ($requestModel->user->company_id !== $company->id) abort(403);

        // shifts（確定）へ反映（上書きOK）
        Shift::updateOrCreate(
            [
                'user_id'    => $requestModel->user_id,
                'shift_date' => $requestModel->shift_date,
            ],
            [
                'store_id'   => $requestModel->store_id,
                'start_time' => $requestModel->is_day_off ? null : $requestModel->start_time,
                'end_time'   => $requestModel->is_day_off ? null : $requestModel->end_time,
                'is_day_off' => $requestModel->is_day_off,
            ]
        );

        $requestModel->update(['status' => 'approved']);

        return back()->with('success', 'シフトリクエストを承認しました。');
    }

    // 単体却下
    public function reject(Company $company, ShiftRequest $requestModel)
    {
        if ($requestModel->user->company_id !== $company->id) abort(403);
        $requestModel->update(['status' => 'rejected']);
        return back()->with('success', 'シフトリクエストを却下しました。');
    }

    // 一括承認（当月のpendingを全部）
    public function approveAll(Company $company, Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));

        $list = ShiftRequest::whereHas('user', fn($q) => $q->where('company_id', $company->id))
            ->where('status', 'pending')
            ->where('shift_date', 'like', $month.'%')
            ->get();

        foreach ($list as $req) {
            Shift::updateOrCreate(
                ['user_id' => $req->user_id, 'shift_date' => $req->shift_date],
                [
                    'store_id'   => $req->store_id,
                    'start_time' => $req->is_day_off ? null : $req->start_time,
                    'end_time'   => $req->is_day_off ? null : $req->end_time,
                    'is_day_off' => $req->is_day_off,
                ]
            );
            $req->update(['status' => 'approved']);
        }

        return back()->with('success', '当月の承認待ちを一括承認しました。');
    }
}
