<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Branch;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function index(Branch $branch)
    {
        return response()->json($branch->bills()->latest()->paginate(20));
    }

    public function show(Branch $branch, Bill $bill)
    {
        $bill->load('payments');
        return response()->json($bill);
    }

    public function store(Request $request, Branch $branch)
    {
        $bill = $branch->bills()->create($request->only([
            'supplier', 'reference_no', 'amount', 'category', 'description', 'bill_date', 'due_date', 'status'
        ]));
        return response()->json(['bill' => $bill], 201);
    }

    public function update(Request $request, Branch $branch, Bill $bill)
    {
        $bill->update($request->only([
            'supplier', 'reference_no', 'amount', 'category', 'description', 'bill_date', 'due_date', 'status'
        ]));
        return response()->json(['bill' => $bill]);
    }

    public function destroy(Branch $branch, Bill $bill)
    {
        $bill->delete();
        return response()->noContent();
    }
}
