<?php

namespace App\Http\Controllers;

use App\Models\CreditSale;
use App\Models\CreditRepayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CreditSaleController extends Controller
{
    /**
     * Display a listing of the credit sales.
     */
    public function index()
    {
        $debtors = CreditSale::with('repayments')
            ->whereColumn('amount_paid', '<', 'total_amount')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'customer' => $sale->customer,
                    'phone' => $sale->customer_phone,
                    'total_credit' => $sale->total_amount,
                    'amount_paid' => $sale->amount_paid,
                    'balance_remaining' => $sale->total_amount - $sale->amount_paid,
                    'repayments' => $sale->repayments,
                ];
            });

        return response()->json($debtors);
    }

    /**
     * Display the specified credit sale.
     */
    public function show(CreditSale $creditSale)
    {
        return response()->json($creditSale->load('repayments'));
    }

    /**
     * Store a new credit sale with normalized phone number.
     */
    /**
     * Store a new credit sale (robust against race conditions).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer'       => 'required|string|max:255',
            'customer_phone' => 'required|string|regex:/^(?:\+?254|0|254)[0-9]{9}$/',
            'total_amount'   => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // ✅ Always normalize phone before querying/saving
        $phone = $this->normalizePhone($request->customer_phone);

        try {
            return \DB::transaction(function () use ($request, $phone) {
                // ✅ Look up existing credit sale for this phone
                $creditSale = CreditSale::where('customer_phone', $phone)->lockForUpdate()->first();

                if ($creditSale) {
                    // Update existing
                    $creditSale->customer = $request->customer;
                    $creditSale->total_amount += $request->total_amount;
                    $creditSale->save();

                    return response()->json($creditSale->load('repayments'), 200);
                }

                // Otherwise create new
                $creditSale = CreditSale::create([
                    'customer'       => $request->customer,
                    'customer_phone' => $phone,
                    'total_amount'   => $request->total_amount,
                    'amount_paid'    => 0.00,
                ]);

                return response()->json($creditSale->load('repayments'), 201);
            });

        } catch (\Exception $e) {
            Log::error('Failed to create/update credit sale', [
                'error' => $e->getMessage(),
                'phone' => $phone,
            ]);
            return response()->json(['error' => 'Failed to create/update credit sale'], 500);
        }
    }

    

    /**
     * Normalize to canonical form (2547XXXXXXXX).
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '0')) {
            $digits = '254' . substr($digits, 1);
        } elseif (! str_starts_with($digits, '254')) {
            $digits = '254' . $digits;
        }

        return $digits;
    }

    /**
     * Detect unique constraint violations across drivers (sqlite/mysql/...).
     */
    private function isUniqueConstraintViolation(\Illuminate\Database\QueryException $e): bool
    {
        $code = (string) $e->getCode();
        $msg  = $e->getMessage();

        // SQLite => code 19 and message contains 'UNIQUE constraint failed'
        // MySQL  => SQLSTATE 23000 and message contains 'Duplicate entry'
        return in_array($code, ['19', '23000', '1062'])
            || str_contains($msg, 'UNIQUE constraint failed')
            || str_contains($msg, 'Duplicate entry');
    }




    /**
     * Process a payment for a credit sale.
     */
    public function pay(Request $request, CreditSale $creditSale)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string',
        ]);
        $amount = (float) $data['amount'];

        if ($amount > ($creditSale->total_amount - $creditSale->amount_paid)) {
            return response()->json(['error' => 'Amount exceeds outstanding balance'], 400);
        }

        $repayment = CreditRepayment::create([
            'credit_sale_id' => $creditSale->id,
            'amount' => $amount,
            'payment_method' => $data['payment_method'] ?? 'Cash',
        ]);

        $creditSale->amount_paid += $amount;
        $creditSale->save();

        $receipt = [
            'receipt_no' => sprintf('RCPT-%05d', $repayment->id),
            'customer' => $creditSale->customer,
            'phone' => $creditSale->customer_phone,
            'total_credit' => (string) $creditSale->total_amount,
            'amount_paid' => (string) $amount,
            'balance_remaining' => (string) ($creditSale->total_amount - $creditSale->amount_paid),
            'payment_method' => $repayment->payment_method,
            'date' => $repayment->created_at->toDateTimeString(),
        ];

        return response()->json(['success' => true, 'receipt' => $receipt], 201);
    }
}