<?php
namespace App\Http\Controllers;
use App\Models\CreditRepayment;
use Illuminate\Http\Request;

class CreditRepaymentController extends Controller
{
    public function index()
    { 
        return response()->json(CreditRepayment::orderBy('created_at','desc')->get()); 
    }
    public function show(CreditRepayment $creditRepayment){ return response()->json($creditRepayment); }

    public function update(Request $r, CreditRepayment $creditRepayment)
    {
        $data = $r->validate(['amount'=>'required|numeric|min:0.01','payment_method'=>'nullable|string']);
        $creditRepayment->amount = $data['amount'];
        $creditRepayment->payment_method = $data['payment_method'] ?? $creditRepayment->payment_method;
        $creditRepayment->save();
        // update credit sale totals
        $credit = $creditRepayment->creditSale;
        $credit->amount_paid = $credit->repayments()->sum('amount');
        $credit->save();
        return response()->json($creditRepayment);
    }
}
