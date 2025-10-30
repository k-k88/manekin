<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shift;
use App\Models\Company;

class ShiftController extends Controller
{
    public function edit(Company $company)
    {
        $shifts = Shift::where('company_id', $company->id)->get();
        return view('company.shifts.edit', compact('company', 'shifts'));
    }

    public function delete(Company $company)
    {
        $shifts = Shift::where('company_id', $company->id)->get();
        return view('company.shifts.delete', compact('company', 'shifts'));
    }
}
