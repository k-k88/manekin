<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Company;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    /**
     * 店舗一覧
     */
    public function index(Company $company)
    {
        $stores = $company->stores()->orderBy('id')->get();
        return view('company.stores.index', compact('company', 'stores'));
    }

    /**
     * 店舗作成フォーム
     */
    public function create(Company $company)
    {
        return view('company.stores.create', compact('company'));
    }

    /**
     * 店舗登録処理
     */
    public function store(Request $request, Company $company)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
        ]);

        $company->stores()->create([
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'status' => $request->status ?? 'active',
        ]);

        return redirect()->route('company.stores.index', $company)
            ->with('success', '店舗を追加しました。');
    }

    /**
     * 店舗編集フォーム
     */
    public function edit(Company $company, Store $store)
    {
        return view('company.stores.edit', compact('company', 'store'));
    }

    /**
     * 店舗更新処理（提出期限対応）
     */
   // 店舗更新処理（提出期限対応）
public function update(Request $request, Company $company, Store $store)
{
    $request->validate([
        'name'  => 'required|string|max:255',
        'address' => 'nullable|string|max:255',
        'phone'   => 'nullable|string|max:255',
        'status'  => 'nullable|in:active,inactive',

        // ← ここを single,half に統一
        'shift_deadline_type'          => 'required|in:single,half',
        'shift_deadline_day'           => 'nullable|integer|min:1|max:31',
        'shift_first_half_deadline'    => 'nullable|integer|min:1|max:31',
        'shift_second_half_deadline'   => 'nullable|integer|min:1|max:31',
    ]);

    // 使わないフィールドは null にクリアしておくと後段のロジックが楽
    $data = [
        'name'   => $request->name,
        'address'=> $request->address,
        'phone'  => $request->phone,
        'status' => $request->status ?? 'active',
        'shift_deadline_type' => $request->shift_deadline_type,
    ];

    if ($request->shift_deadline_type === 'single') {
        $data['shift_deadline_day']         = $request->shift_deadline_day;
        $data['shift_first_half_deadline']  = null;
        $data['shift_second_half_deadline'] = null;
    } else { // half
        $data['shift_deadline_day']         = null;
        $data['shift_first_half_deadline']  = $request->shift_first_half_deadline;
        $data['shift_second_half_deadline'] = $request->shift_second_half_deadline;
    }

    $store->update($data);

    return redirect()->route('company.stores.index', $company)
        ->with('success', '店舗情報を更新しました。');
}

    /**
     * 店舗削除
     */
    public function destroy(Company $company, Store $store)
    {
        $store->delete();

        return redirect()->route('company.stores.index', $company)
            ->with('success', '店舗を削除しました。');
    }
}
