<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillPaymentController extends Controller
{
    /**
     * Store a payment for a bill.
     */
    public function store(Request $request, Branch $branch, Bill $bill)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'note'   => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($branch, $bill, $validated, $request) {
            // 💰 1. Create the payment record
            $payment = Payment::create([
                'branch_id'      => $branch->id,
                'amount'         => $validated['amount'],
                'method'         => 'cash', // or 'mpesa' if you add logic later
                'status'         => 'completed',
                'user_id'        => $request->user()->id,
                'transaction_id' => 'BILL-' . strtoupper(uniqid()),
                'note'           => $validated['note'] ?? null,
            ]);

            // 📘 2. Link payment to bill and mark as paid
            $bill->update([
                'status'     => 'Paid',
                'paid_at'    => now(),
                'payment_id' => $payment->id,
            ]);

            // 📒 3. Create an expense for this bill
            $expense = Expense::create([
                'branch_id' => $branch->id,
                'title'     => 'Bill Payment: ' . ($bill->supplier ?? 'Unknown Supplier'),
                'amount'    => $validated['amount'],
                'category'  => $bill->category ?? 'Miscellaneous',
                'note'      => $validated['note'] ?? ('Payment for bill #' . $bill->id),
                'user_id'   => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Bill payment recorded successfully.',
                'bill'    => $bill->fresh(['payments']),
                'payment' => $payment,
                'expense' => $expense,
            ], 201);
        });
    }

    /**
     * List all payments for a bill.
     */
    public function index(Branch $branch, Bill $bill)
    {
        return response()->json($bill->payments()->latest()->get());
    }
}
