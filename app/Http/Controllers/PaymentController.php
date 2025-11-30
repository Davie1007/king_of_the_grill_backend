<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Branch;
use App\Models\Bill;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // add at the top

class PaymentController extends Controller
{
    /**
     * List all payments for a specific branch
     */
    public function index(Request $request, $branchId)
    {
        $branch = Branch::findOrFail($branchId);

        $query = Sale::with(['creditSale', 'saleItems'])
            ->where('branch_id', $branch->id);

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $payments = $query->orderBy('created_at','desc')->paginate(20);

        // transform each item:
        $payments->getCollection()->transform(function ($sale) {
            return [
                'id'                => $sale->id,
                'customer_name'     => $sale->customer_name,
                'customer_phone'    => $sale->customer_telephone_number,
                'payment_method'    => $sale->payment_method,
                'total'             => $sale->total,
                'cash_tendered'     => $sale->cash_tendered,
                'change'            => $sale->change,
                'payment_status'    => $sale->payment_status,
                'credit_sale_id'    => $sale->credit_sale_id,
                'created_at'        => $sale->created_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'branch_id'   => $branch->id,
            'branch_name' => $branch->name,
            'payments'    => $payments
        ]);
    }


    /**
     * Show a single payment (sale) with full details
     */
    public function show($branchId, $saleId)
    {
        $branch = Branch::findOrFail($branchId);

        $sale = Sale::with(['saleItems', 'creditSale'])
            ->where('branch_id', $branch->id)
            ->findOrFail($saleId);

        return response()->json([
            'id'                => $sale->id,
            'branch'            => $branch->name,
            'customer_name'     => $sale->customer_name,
            'customer_phone'    => $sale->customer_telephone_number,
            'payment_method'    => $sale->payment_method,
            'total'             => $sale->total,
            'cash_tendered'     => $sale->cash_tendered,
            'change'            => $sale->change,
            'payment_status'    => $sale->payment_status,
            'credit_sale'       => $sale->creditSale,   // includes repayments if eager-loaded
            'items'             => $sale->saleItems,
            'created_at'        => $sale->created_at->toDateTimeString(),
        ]);
    }

    

    public function grouped($branchId, Request $request)
    {
        $branch = Branch::findOrFail($branchId);
        $period = $request->query('period', 'daily'); // daily, weekly, etc.
    
        $query = Sale::where('branch_id', $branch->id);
    
        switch ($period) {
            case 'weekly':
                // SQLite: strftime('%Y-%W', created_at)
                $groupBy = DB::raw("strftime('%Y-%W', created_at) as period");
                break;
            case 'monthly':
                // SQLite: strftime('%Y-%m', created_at)
                $groupBy = DB::raw("strftime('%Y-%m', created_at) as period");
                break;
            case 'yearly':
                // SQLite: strftime('%Y', created_at)
                $groupBy = DB::raw("strftime('%Y', created_at) as period");
                break;
            default:
                // SQLite: date(created_at)
                $groupBy = DB::raw("date(created_at) as period");
                break;
        }
    
        $grouped = $query
            ->select(
                $groupBy,
                'payment_method',
                DB::raw('SUM(total) as total_amount')
            )
            ->groupBy('period', 'payment_method')
            ->orderByRaw('MIN(created_at) desc')
            ->get();
    
        return response()->json($grouped);
    }
        /**
     * Record a payment for a Bill
     */
    public function payBill(Request $request, $branchId)
    {
        $branch = Branch::findOrFail($branchId);

        $validated = $request->validate([
            'bill_id'   => 'required|exists:bills,id',
            'amount'    => 'required|numeric|min:1',
            'method'    => 'required|string',
            'reference' => 'nullable|string',
        ]);

        $bill = Bill::where('branch_id', $branch->id)->findOrFail($validated['bill_id']);

        // Create payment record
        $payment = Payment::create([
            'branch_id' => $branch->id,
            'bill_id'   => $bill->id,
            'amount'    => $validated['amount'],
            'method'    => $validated['method'],
            'reference' => $validated['reference'] ?? 'Bill Payment #' . $bill->id,
        ]);

        // Update bill payment summary
        $bill->updatePaymentStatus();

        return response()->json([
            'message' => 'Bill payment recorded successfully.',
            'payment' => $payment,
            'bill'    => $bill->fresh(),
        ], 201);
    }

    /**
     * List all bill payments for a branch
     */
    public function billPayments($branchId)
    {
        $branch = Branch::findOrFail($branchId);

        $payments = Payment::with('bill')
            ->where('branch_id', $branch->id)
            ->whereNotNull('bill_id')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'branch_id'   => $branch->id,
            'branch_name' => $branch->name,
            'bill_payments' => $payments,
        ]);
    }

    public function search($query)
    {
        $payment = \App\Models\Payment::with('sale')->where('transaction_id', $query)->first();

        if (!$payment) {
            // Also try matching Sale ID
            $sale = \App\Models\Sale::with(['saleItems', 'branch'])->find($query);
            if (!$sale) {
                return response()->json(['error' => 'No payment or sale found for that reference.'], 404);
            }
            return response()->json([
                'sale' => $sale,
                'payment' => $sale->payment,
            ]);
        }

        return response()->json([
            'payment' => $payment,
            'sale' => $payment->sale,
        ]);
    }

    public function suggestions(Request $request)
    {
        $type = $request->query('type', 'transaction');
        $q = $request->query('q', '');

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        if ($type === 'transaction') {
            $results = Payment::where('transaction_id', 'like', "%{$q}%")
                ->orderByDesc('id')
                ->limit(10)
                ->pluck('transaction_id');
        } else {
            $results = Sale::where('id', 'like', "%{$q}%")
                ->orderByDesc('id')
                ->limit(10)
                ->pluck('id');
        }

        return response()->json($results);
    }


}

