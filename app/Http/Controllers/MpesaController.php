<?php

namespace App\Http\Controllers;

use App\Events\PaymentReceived;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\CreditSale;
use App\Models\InventoryItem;
use App\Models\CreditRepayment;
use App\Models\Payment;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Events\PaymentFailed;

class MpesaController extends Controller
{
    protected $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    /**
     * Initiate C2B Payment (for simulation or specific workflows)
     */
    public function initiateC2BPayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'tillNumber' => 'required|string',
            'branch_id' => 'required|exists:branches,id',
            'items' => 'required|array|min:1',
            'items.*.item' => 'required|exists:inventory_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'customer_telephone' => 'nullable|string',
        ]);

        try {
            $amount = $request->amount;
            $tillNumber = $request->tillNumber;
            $branchId = $request->branch_id;
            $items = $request->items;
            $customerTelephone = $request->customer_telephone ? $this->normalizePhone($request->customer_telephone) : null;

            // Validate stock
            foreach ($items as $item) {
                $inv = InventoryItem::find($item['item']);
                if (!$inv || $inv->stock < $item['quantity']) {
                    Log::error('Insufficient stock for item', ['item_id' => $item['item']]);
                    return response()->json(['error' => "Insufficient stock for item {$item['item']}"], 400);
                }
            }

            // Generate unique cart reference
            $cartRef = 'C2B_' . uniqid();
            $cartData = [
                'amount' => (float) $amount,
                'branch_id' => (int) $branchId,
                'items' => $items,
                'customer_telephone' => $customerTelephone,
                'created_at' => now()->toDateTimeString(),
                'cart_ref' => $cartRef,
            ];

            // Store cart in cache (TTL: 10 minutes)
            Cache::put($cartRef, $cartData, now()->addMinutes(10));

            // Add cart_ref to pending carts list
            $pendingCarts = Cache::get('pending_carts', []);
            $pendingCarts[$cartRef] = [
                'expires_at' => now()->addMinutes(10)->toDateTimeString(),
            ];
            Cache::put('pending_carts', array_filter($pendingCarts, function ($cart) {
                return now()->lessThanOrEqualTo($cart['expires_at']);
            }), now()->addDays(1));

            // Store pending payment
            $transactionId = 'SIM' . uniqid();
            $payment = $this->recordPayment($transactionId, $amount, $customerTelephone, $cartRef);

            Log::info('Manual C2B payment initiated', [
                'transaction_id' => $transactionId,
                'cart_ref' => $cartRef,
                'amount' => $amount,
                'till_number' => $tillNumber,
                'branch_id' => $branchId,
            ]);

            return response()->json([
                'status' => 'pending',
                'transactionId' => $transactionId,
                'cartRef' => $cartRef,
                'message' => "Please pay KES {$amount} to Till Number: {$tillNumber} with Account Number {$cartRef}",
            ]);
        } catch (\Exception $e) {
            Log::error('Error initiating C2B payment', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to initiate payment'], 500);
        }
    }

    /**
     * Handle M-Pesa STK Push callback
     */

    public function stkCallback(Request $request, $appId = null)
    {
        Log::info('M-Pesa STK Callback', ['app_id' => $appId, 'payload' => $request->all()]);

        $body = $request->input('Body.stkCallback') ?? ($request->input('Body')['stkCallback'] ?? null);
        if (!$body) {
            Log::error('Missing STK payload', $request->all());
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Missing payload']);
        }

        if (($body['ResultCode'] ?? 1) != 0) {
            Log::warning('STK Push failed', $body);
            // Broadcast failure to frontend
            $cartRef = $body['AccountReference'] ?? $body['CheckoutRequestID'] ?? 'unknown';
            broadcast(new PaymentFailed($cartRef, $body['ResultCode'], $body['ResultDesc']))->toOthers();
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Noted']);
        }

        $items = collect($body['CallbackMetadata']['Item'] ?? []);
        $amount = $items->firstWhere('Name', 'Amount')['Value'] ?? 0;
        $mpesaRef = $items->firstWhere('Name', 'MpesaReceiptNumber')['Value'] ?? '';
        $phone = $items->firstWhere('Name', 'PhoneNumber')['Value'] ?? '';

        $billRef = $body['AccountReference'] ?? null;
        if (!$billRef) {
            Log::warning('AccountReference missing in STK callback, falling back to CheckoutRequestID', [
                'CheckoutRequestID' => $body['CheckoutRequestID'] ?? 'N/A',
            ]);
            $billRef = $body['CheckoutRequestID'] ?? '';
        }

        return $this->handleMpesaPayment(
            $mpesaRef,
            (float) $amount,
            $this->normalizePhone($phone),
            $billRef
        );
    }

    /**
     * Handle M-Pesa C2B confirmation (initiation and callback)
     */
    public function c2bConfirmation(Request $request)
    {
        Log::info('M-Pesa C2B Confirmation', $request->all());

        $transactionId = trim((string) $request->get('TransID', ''));
        $amount = (float) $request->get('TransAmount', 0);
        $phone = $request->get('MSISDN', '');
        $billRef = $request->get('BillRefNumber', '');

        $normalizedPhone = $this->normalizePhone($phone);

        try {
            DB::beginTransaction();

            // Duplicate protection (early)
            $existingPayment = Payment::where('transaction_id', $transactionId)->first();
            if ($existingPayment) {
                Log::info('Transaction already processed', ['transaction_id' => $transactionId]);
                DB::commit();
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Transaction already processed']);
            }

            // Record payment (always create with used = false)
            $payment = $this->recordPayment($transactionId, $amount, $normalizedPhone, $billRef);
            Log::info('Payment created (confirmation)', ['payment_id' => $payment->id, 'transaction_id' => $transactionId]);

            // Try to match to a pending cart
            $cartKey = $this->findPendingCart($amount, $normalizedPhone, $billRef);

            // persist pending cart changes and commit before delegating to handlers
            DB::commit();

            if ($cartKey) {
                $cart = Cache::pull($cartKey);
                // delegate to handler that will process sale and mark payment used
                return $this->handleMpesaPayment($transactionId, $amount, $normalizedPhone, $billRef, $cart, $payment);
            }

            // Check for credit repayment (we do this after commit because we created Payment)
            $creditSale = CreditSale::where('customer_phone', $normalizedPhone)
                ->whereColumn('amount_paid', '<', 'total_credit')
                ->first();

            if ($creditSale) {
                // handleCreditRepayment will run its own transaction and mark payment used
                return $this->handleCreditRepayment($transactionId, $amount, $normalizedPhone, $billRef, $creditSale, $payment);
            }

            // Check for existing sale that expects payment (immediate application)
            $sale = Sale::where('customer_telephone_number', $normalizedPhone)
                ->where('amount_paid', '<', DB::raw('total'))
                ->first();

            if ($sale) {
                // Apply payment immediately
                DB::beginTransaction();
                try {
                    $repaymentAmount = min($amount, $sale->total - $sale->amount_paid);
                    $sale->amount_paid += $repaymentAmount;
                    $sale->save();

                    $sale->update([
                        'payment_id' => $payment->id,
                        'mpesa_ref' => $payment->transaction_id,
                    ]);


                    $payment->sale_id = $sale->id;
                    $payment->used = true;
                    $payment->save();

                    $receipt = [
                        'receipt_no' => $transactionId,
                        'branch' => ['name' => $sale->branch->name ?? 'Branch'],
                        'timestamp' => now()->toDateTimeString(),
                        'payment_method' => 'M-Pesa',
                        'total' => $sale->total,
                        'amount_paid' => $repaymentAmount,
                        'customer_telephone' => $normalizedPhone,
                        'transactionId' => $transactionId,
                        'sale_id' => $sale->id,
                    ];

                    broadcast(new PaymentReceived($sale, $repaymentAmount, $transactionId, $receipt))->toOthers();

                    DB::commit();
                    Log::info('Sale payment processed successfully (confirmation)', ['transaction_id' => $transactionId, 'sale_id' => $sale->id, 'amount' => $repaymentAmount]);

                    return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Sale payment processed successfully']);
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Error applying payment to existing sale (confirmation)', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                    return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Error processing transaction'], 500);
                }
            }

            // Default: stored unlinked (payment already created)
            Log::info('Unlinked C2B payment stored for verification', [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'phone' => $normalizedPhone,
            ]);

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Payment stored for verification']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('M-Pesa C2B Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Error processing transaction']);
        }
    }

    /**
     * Handle credit repayment (used for both home payments and frontend-initiated)
     * This uses DB::transaction internally to keep consistency.
     */
    protected function handleCreditRepayment($mpesaRef, $amount, $phone, $billRef, $creditSale, $payment)
    {
        try {
            return DB::transaction(function () use ($mpesaRef, $amount, $phone, $billRef, $creditSale, $payment) {
                $expectedAmount = $creditSale->balance; // Use balance accessor
                if ($amount > $expectedAmount + 0.01) {
                    Log::error('Overpayment detected for credit repayment', [
                        'transaction_id' => $mpesaRef,
                        'paid_amount' => $amount,
                        'expected_amount' => $expectedAmount,
                        'credit_sale_id' => $creditSale->id,
                    ]);
                    return response()->json([
                        'ResultCode' => 1,
                        'ResultDesc' => 'Overpayment detected',
                        'expected_amount' => $expectedAmount,
                        'paid_amount' => $amount,
                    ], 400);
                }

                $creditSale->amount_paid += $amount;
                $creditSale->save(); // No need for balance_remaining if using balance accessor

                $repayment = CreditRepayment::create([
                    'credit_sale_id' => $creditSale->id,
                    'amount' => $amount,
                    'payment_method' => 'M-Pesa',
                    'transaction_id' => $mpesaRef,
                    'payment_id' => $payment->id,
                ]);

                // Mark the payment consumed
                $payment->used = true;
                $payment->save();

                $receipt = [
                    'receipt_no' => $mpesaRef,
                    'customer' => $creditSale->customer_name ?? 'Customer',
                    'phone' => $phone,
                    'total_credit' => $creditSale->total_amount, // Fix to total_amount
                    'amount_paid' => $amount,
                    'balance_remaining' => $creditSale->balance, // Use balance accessor
                    'payment_method' => 'M-Pesa',
                    'date' => now()->toDateTimeString(),
                    'transactionId' => $mpesaRef,
                    'credit_sale_id' => $creditSale->id,
                ];

                Log::info('Credit repayment processed successfully', [
                    'transaction_id' => $mpesaRef,
                    'credit_sale_id' => $creditSale->id,
                    'amount' => $amount,
                ]);

                broadcast(new PaymentReceived($creditSale, $amount, $mpesaRef, $receipt))->toOthers();

                return response()->json([
                    'ResultCode' => 0,
                    'ResultDesc' => 'Accepted',
                    'receipt' => $receipt,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Credit repayment processing error', [
                'error' => $e->getMessage(),
                'billRef' => $billRef,
                'transaction_id' => $mpesaRef,
            ]);
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Processing error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Common logic to process M-Pesa payments (STK or C2B for sales)
     *
     * If $payment is provided it will be used (expected to exist).
     * If $cart is null, we'll attempt to pull cart using $billRef.
     */
    protected function handleMpesaPayment($mpesaRef, $amount, $phone, $billRef, $cart = null, $payment = null)
    {
        try {
            // Ensure a payment record exists for this mpesaRef; if not, create it (used=false).
            if (!$payment) {
                $payment = Payment::firstOrCreate(
                    ['transaction_id' => $mpesaRef],
                    [
                        'amount' => $amount,
                        'phone' => $phone,
                        'method' => 'M-Pesa',
                        'used' => false,
                        'cart_ref' => $billRef,
                    ]
                );
            }

            if (!$cart) {
                $cart = Cache::pull($billRef);
                if (!$cart) {
                    Log::error('Cart not found for ref', ['billRef' => $billRef, 'transaction_id' => $mpesaRef]);
                    // leave payment unused so it can be verified later
                    $payment->used = false;
                    $payment->save();
                    return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Cart not found']);
                }
            }

            $creditSaleId = $cart['credit_sale_id'] ?? null;

            return DB::transaction(function () use ($mpesaRef, $amount, $phone, $cart, $creditSaleId, $payment, $billRef) {
                if ($creditSaleId) {
                    // handle credit repayment within a nested transaction call
                    return $this->handleCreditRepayment($mpesaRef, $amount, $phone, $cart['transactionId'] ?? $billRef, CreditSale::find($creditSaleId), $payment);
                }

                // Validate payment amount against cart total
                if (abs($amount - $cart['amount']) > 0.01) {
                    Log::error('Payment amount mismatch for sale', [
                        'transaction_id' => $mpesaRef,
                        'paid_amount' => $amount,
                        'expected_amount' => $cart['amount'],
                    ]);
                    return response()->json([
                        'ResultCode' => 1,
                        'ResultDesc' => $amount < $cart['amount'] ? 'Underpayment detected' : 'Overpayment detected',
                        'expected_amount' => $cart['amount'],
                        'paid_amount' => $amount,
                    ], 400);
                }

                // Pre-check stock for all items before creating sale
                foreach ($cart['items'] as $item) {
                    $inv = InventoryItem::find($item['item']);
                    if (!$inv) {
                        Log::error('Inventory item not found (during handleMpesaPayment)', ['item' => $item]);
                        DB::rollBack();
                        return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Inventory item not found'], 404);
                    }
                    if ($inv->stock < $item['quantity']) {
                        Log::error('Insufficient stock for item (during handleMpesaPayment)', [
                            'item_id' => $item['item'],
                            'stock' => $inv->stock,
                            'requested' => $item['quantity'],
                            'transaction_id' => $mpesaRef,
                        ]);
                        DB::rollBack();
                        return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Insufficient stock for item'], 400);
                    }
                }

                // Create sale
                $sale = Sale::create([
                    'branch_id' => $cart['branch_id'],
                    'payment_method' => 'M-Pesa',
                    'total' => $cart['amount'],
                    'customer_telephone_number' => $phone,
                    'payment_status' => 'Paid',
                    'mpesa_ref' => $mpesaRef,
                    'mpesa_amount' => $amount,
                    'payment_id' => $payment->id,
                ]);

                // create sale items and decrement stock
                foreach ($cart['items'] as $item) {
                    $inv = InventoryItem::find($item['item']);
                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'item_id' => $inv->id,
                        'quantity' => (float) $item['quantity'],
                        'price' => (float) $item['price'],
                    ]);
                    $inv->stock -= (float) $item['quantity'];
                    $inv->save();
                }

                // mark payment consumed
                $payment->used = true;
                $payment->cart_ref = $cart['cart_ref'] ?? $billRef;
                $payment->save();

                $receipt = [
                    'receipt_no' => $mpesaRef,
                    'branch' => ['name' => $sale->branch->name ?? 'Branch'],
                    'timestamp' => now()->toDateTimeString(),
                    'items' => $cart['items'],
                    'payment_method' => 'M-Pesa',
                    'total' => $cart['amount'],
                    'customer_telephone' => $phone,
                    'transactionId' => $mpesaRef,
                    'sale_id' => $sale->id,
                    'cart_ref' => $cart['cart_ref'] ?? $billRef,
                ];

                Log::info('Sale processed successfully', [
                    'transaction_id' => $mpesaRef,
                    'sale_id' => $sale->id,
                    'amount' => $amount,
                    'cart_ref' => $cart['cart_ref'] ?? $billRef,
                ]);

                Log::info('Broadcasting PaymentReceived event', [
                    'channel' => 'payments.' . ($sale->branch_id ?? 'unknown'),
                    'sale_id' => $sale->id,
                    'mpesa_ref' => $mpesaRef
                ]);

                broadcast(new PaymentReceived($sale, $amount, $mpesaRef, $receipt))->toOthers();

                return response()->json([
                    'ResultCode' => 0,
                    'ResultDesc' => 'Accepted',
                    'receipt' => $receipt,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('M-Pesa payment processing error', [
                'error' => $e->getMessage(),
                'billRef' => $billRef,
                'transaction_id' => $mpesaRef,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Processing error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Verify M-Pesa transaction (called by frontend to apply a stored payment)
     *
     * This will consume (mark used=true) the payment if valid and create the Sale / CreditRepayment.
     */
    public function verifyTransaction(Request $request)
    {
        Log::info('Verify Transaction Request Received', [
            'method' => $request->method(),
            'path' => $request->path(),
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        $request->validate([
            'transaction_id' => 'required|string',
            'context' => 'required|string|in:cart,credit',
            'credit_sale_id' => 'nullable|integer|required_if:context,credit',
            'branch_id' => 'nullable|integer|required_if:context,cart',
            'items' => 'nullable|array|required_if:context,cart',
            'items.*.item' => 'required_with:items|integer',
            'items.*.quantity' => 'required_with:items|numeric|min:1',
            'items.*.price' => 'required_with:items|numeric|min:0',
        ]);

        $transactionId = trim((string) $request->transaction_id);

        try {
            return DB::transaction(function () use ($request, $transactionId) {
                $txn = Payment::where('transaction_id', $transactionId)->lockForUpdate()->first();

                if (!$txn) {
                    Log::error('Transaction not found in payments table', ['transaction_id' => $transactionId]);
                    return response()->json(['error' => 'Transaction not found in payments table'], 404);
                }

                if ($txn->used) {
                    Log::error('Transaction already used', ['transaction_id' => $transactionId]);
                    return response()->json(['error' => 'Transaction already used'], 400);
                }

                // CART context: create sale, decrement stock, mark payment used
                if ($request->context === 'cart') {
                    $branchId = $request->branch_id;
                    if (!DB::table('branches')->where('id', $branchId)->exists()) {
                        Log::error('Invalid branch_id', ['branch_id' => $branchId, 'transaction_id' => $transactionId]);
                        return response()->json(['error' => 'Invalid branch_id'], 400);
                    }

                    $items = $request->items ?? [];
                    $expectedTotal = collect($items)->sum(function ($item) {
                        return (float) $item['quantity'] * (float) $item['price'];
                    });

                    if (abs($txn->amount - $expectedTotal) > 0.01) {
                        Log::error('Payment amount mismatch for cart', [
                            'transaction_id' => $transactionId,
                            'paid_amount' => $txn->amount,
                            'expected_amount' => $expectedTotal,
                        ]);
                        return response()->json([
                            'error' => $txn->amount < $expectedTotal ? 'Underpayment detected' : 'Overpayment detected',
                            'expected_amount' => $expectedTotal,
                            'paid_amount' => $txn->amount,
                        ], 400);
                    }

                    // Pre-check stock
                    foreach ($items as $item) {
                        $inventoryItem = InventoryItem::find($item['item']);
                        if (!$inventoryItem) {
                            Log::error('Inventory item not found', ['item_id' => $item['item'], 'transaction_id' => $transactionId]);
                            return response()->json(['error' => 'Inventory item not found'], 404);
                        }
                        if ($inventoryItem->stock < $item['quantity']) {
                            Log::error('Insufficient stock for item', [
                                'item_id' => $item['item'],
                                'stock' => $inventoryItem->stock,
                                'requested' => $item['quantity'],
                                'transaction_id' => $transactionId,
                            ]);
                            return response()->json(['error' => 'Insufficient stock for item'], 400);
                        }
                    }

                    // Create sale
                    $sale = Sale::create([
                        'branch_id' => $branchId,
                        'payment_method' => 'M-Pesa',
                        'total' => $expectedTotal,
                        'customer_telephone_number' => $txn->phone,
                        'payment_status' => 'Paid',
                        'mpesa_ref' => $txn->transaction_id,
                        'mpesa_amount' => $txn->amount,
                        'payment_id' => $txn->id,
                    ]);

                    foreach ($items as $item) {
                        $inventoryItem = InventoryItem::find($item['item']);
                        SaleItem::create([
                            'sale_id' => $sale->id,
                            'item_id' => $inventoryItem->id,
                            'quantity' => (float) $item['quantity'],
                            'price' => (float) $item['price'],
                        ]);
                        $inventoryItem->stock -= (float) $item['quantity'];
                        $inventoryItem->save();
                    }

                    // mark txn used
                    $txn->used = true;
                    $txn->save();

                    DB::commit();

                    $receipt = [
                        'receipt_no' => $txn->transaction_id,
                        'branch' => ['name' => $sale->branch->name ?? 'Branch'],
                        'timestamp' => now()->toDateTimeString(),
                        'items' => $items,
                        'payment_method' => 'M-Pesa',
                        'total' => $expectedTotal,
                        'customer_telephone' => $txn->phone,
                        'transactionId' => $txn->transaction_id,
                        'sale_id' => $sale->id,
                    ];

                    broadcast(new PaymentReceived($sale, $txn->amount, $txn->transaction_id, $receipt))->toOthers();

                    return response()->json([
                        'status' => 'verified',
                        'context' => 'cart',
                        'amount' => $txn->amount,
                        'transaction_id' => $txn->transaction_id,
                        'sale_id' => $sale->id,
                        'receipt' => $receipt,
                    ]);
                }

                // CREDIT context: attach to given credit_sale_id
                if ($request->context === 'credit' && $request->credit_sale_id) {
                    $creditSale = CreditSale::find($request->credit_sale_id);
                    if (!$creditSale) {
                        Log::error('Credit sale not found', ['credit_sale_id' => $request->credit_sale_id, 'transaction_id' => $transactionId]);
                        return response()->json(['error' => 'Credit sale not found'], 404);
                    }

                    // Validate total_amount and amount_paid
                    if (is_null($creditSale->total_amount) || is_null($creditSale->amount_paid)) {
                        Log::error('Invalid credit sale data', [
                            'credit_sale_id' => $creditSale->id,
                            'total_amount' => $creditSale->total_amount,
                            'amount_paid' => $creditSale->amount_paid,
                        ]);
                        return response()->json(['error' => 'Invalid credit sale data'], 400);
                    }

                    if ($txn->amount > $creditSale->balance + 0.01) {
                        Log::error('Overpayment detected for credit repayment', [
                            'transaction_id' => $transactionId,
                            'paid_amount' => $txn->amount,
                            'expected_amount' => $creditSale->balance,
                            'credit_sale_id' => $creditSale->id,
                        ]);
                        return response()->json([
                            'error' => 'Overpayment detected',
                            'expected_amount' => $creditSale->balance,
                            'paid_amount' => $txn->amount,
                        ], 400);
                    }

                    $repayment = CreditRepayment::create([
                        'credit_sale_id' => $creditSale->id,
                        'amount' => $txn->amount,
                        'payment_method' => 'M-Pesa',
                        'transaction_id' => $txn->transaction_id,
                        'payment_id' => $txn->id,
                    ]);

                    $creditSale->amount_paid += $txn->amount;
                    $creditSale->save();

                    $txn->used = true;
                    $txn->save();

                    $receipt = [
                        'receipt_no' => $txn->transaction_id,
                        'customer' => $creditSale->customer_name ?? 'Customer',
                        'phone' => $txn->phone,
                        'total_credit' => $creditSale->total_amount, // Fix to total_amount
                        'amount_paid' => $txn->amount,
                        'balance_remaining' => $creditSale->balance, // Use balance accessor
                        'payment_method' => 'M-Pesa',
                        'date' => now()->toDateTimeString(),
                        'transactionId' => $txn->transaction_id,
                        'credit_sale_id' => $creditSale->id,
                    ];

                    broadcast(new PaymentReceived($creditSale, $txn->amount, $txn->transaction_id, $receipt))->toOthers();

                    return response()->json([
                        'status' => 'verified',
                        'context' => 'credit',
                        'amount' => $txn->amount,
                        'transaction_id' => $txn->transaction_id,
                        'credit_sale_id' => $creditSale->id,
                        'receipt' => $receipt,
                    ]);
                }

                // If we got here context was invalid
                Log::error('Invalid context provided', ['context' => $request->context, 'transaction_id' => $transactionId]);
                return response()->json(['error' => 'Invalid context'], 400);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Transaction verification failed', [
                'transaction_id' => $transactionId,
                'context' => $request->context,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Verification failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Register C2B URLs with M-Pesa
     */
    public function registerC2BUrls(Request $request)
    {
        try {
            $url = $this->mpesaService->getEnv() === 'live'
                ? 'https://api.safaricom.co.ke/mpesa/c2b/v1/registerurl'
                : 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->mpesaService->getAccessToken(),
            ])->post($url, [
                        'ShortCode' => $this->mpesaService->getShortcode(),
                        'ResponseType' => 'Completed',
                        'ConfirmationURL' => env('APP_URL') . '/api/mpesa/c2b/confirmation',
                        'ValidationURL' => env('APP_URL') . '/api/mpesa/c2b/validation',
                    ]);

            if ($response->successful()) {
                Log::info('C2B URLs registered successfully', $response->json());
                return response()->json(['message' => 'C2B URLs registered successfully']);
            }

            Log::error('C2B URL registration failed', $response->json());
            return response()->json(['error' => 'Failed to register C2B URLs'], 500);
        } catch (\Exception $e) {
            Log::error('C2B URL registration error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Registration error'], 500);
        }
    }

    /**
     * Optional: Handle C2B validation callback
     */
    public function c2bValidation(Request $request)
    {
        Log::info('M-Pesa C2B Validation Callback', $request->all());
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    /* -------------------------
     * Helper utilities
     * ------------------------- */

    private function normalizePhone(?string $phone): ?string
    {
        if (is_null($phone) || $phone === '')
            return null;
        $phone = trim($phone);
        $phone = preg_replace('/\s+/', '', $phone);
        if (substr($phone, 0, 1) === '+') {
            $phone = substr($phone, 1);
        }
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }
        return $phone;
    }

    /**
     * Create or ensure payment exists. Always create with used = false (unless explicitly passed).
     */
    private function recordPayment(string $transactionId, float $amount, ?string $phone = null, ?string $cartRef = null): Payment
    {
        $transactionId = trim($transactionId);
        $payment = Payment::firstOrCreate(
            ['transaction_id' => $transactionId],
            [
                'amount' => $amount,
                'phone' => $phone,
                'method' => 'M-Pesa',
                'used' => false,
                'cart_ref' => $cartRef,
            ]
        );

        // Ensure used is false on initial creation; don't flip used back if present.
        if (!$payment->wasRecentlyCreated && $payment->used === null) {
            $payment->used = false;
            $payment->save();
        }

        return $payment;
    }

    /**
     * Try to find a pending cart matching amount, phone and/or billRef.
     * Returns the cache key (cartRef) or null.
     */
    private function findPendingCart(float $amount, ?string $phone = null, ?string $billRef = null): ?string
    {
        $pendingCarts = Cache::get('pending_carts', []);
        $cartKey = null;
        foreach ($pendingCarts as $key => $meta) {
            // remove expired entries from the index (but do not persist here; c2bConfirmation persists later)
            if (now()->greaterThan($meta['expires_at'])) {
                unset($pendingCarts[$key]);
                continue;
            }
            $cart = Cache::get($key);
            if (!$cart)
                continue;

            if (
                abs($cart['amount'] - $amount) <= 0.01
                && (empty($cart['customer_telephone']) || $cart['customer_telephone'] === $phone)
                && ($billRef === ($cart['cart_ref'] ?? $billRef))
            ) {
                $cartKey = $key;
                break;
            }
        }

        // update pending_carts sanitized (caller should persist)
        if ($cartKey) {
            // caller will pull the cart; we just return the key
            return $cartKey;
        }

        return null;
    }
}
