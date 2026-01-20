<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankDetail;
use Illuminate\Http\Request;

class BankDetailController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => BankDetail::latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bank_name' => 'required|string',
            'account_holder_name' => 'required|string',
            'account_number' => 'required|string',
            'ifsc_code' => 'required|string',
            'swift_code' => 'nullable|string',
            'account_type' => 'nullable|string',
        ]);

        $bank = BankDetail::create($validated);

        return response()->json([
            'message' => 'Bank details added successfully',
            'data' => $bank
        ], 201);
    }

    public function show($id)
    {
        return response()->json([
            'data' => BankDetail::findOrFail($id)
        ]);
    }

    public function update(Request $request, $id)
    {
        $bank = BankDetail::findOrFail($id);

        $bank->update($request->only([
            'bank_name',
            'account_holder_name',
            'account_number',
            'ifsc_code',
            'swift_code',
            'account_type',
        ]));

        return response()->json([
            'message' => 'Bank details updated successfully',
            'data' => $bank
        ]);
    }

    public function destroy($id)
    {
        BankDetail::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Bank details deleted successfully'
        ]);
    }
}
