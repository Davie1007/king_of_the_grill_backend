<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\InventoryItem;
use App\Models\CreditSale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\DynamicMpesaService;
use Illuminate\Support\Facades\Cache;
use App\Notifications\LargeTransactionNotification;
use App\Models\User;

class SaleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['productPerformance']);
        Log::info('constructor called');
    }

    public function index()
    {
        Log::info('SaleController::index called');
        return response()->json(Sale::with('saleItems.item')->orderBy('created_at', 'desc')->get());
    }

    public function show(Sale $sale)
    {
        Log::info('SaleController::show called', ['sale_id' => $sale->id]);

        try {
            $sale->load([
                'saleItems.item',
                'branch:id,name',
                'creditSale',
            ]);

            $data = [
                'id' => $sale->id,
                'branch' => $sale->branch?->name,
                'payment_method' => $sale->payment_method,
                'payment_status' => $sale->payment_status,
                'total' => $sale->total,
                'cash_tendered' => $sale->cash_tendered,
                'change' => $sale->change,
                'customer_name' => $sale->customer_name,
                'customer_telephone_number' => $sale->customer_telephone_number,
                'created_at' => $sale->created_at,
                'updated_at' => $sale->updated_at,
                'items' => $sale->saleItems->map(function ($i) {
                    return [
                        'item' => $i->item->name ?? 'Unknown',
                        'price' => $i->price,
                        'quantity' => $i->quantity,
                        'total' => round($i->price * $i->quantity, 2),
                    ];
                }),
                'credit_sale' => $sale->creditSale ? [
                    'total_amount' => $sale->creditSale->total_amount,
                    'amount_paid' => $sale->creditSale->amount_paid,
                    'balance' => $sale->creditSale->total_amount - $sale->creditSale->amount_paid,
                ] : null,
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('SaleController::show failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Unable to fetch sale details'], 500);
        }
    }

    public function branchSales($branchId)
    {
        $sales = Sale::with('saleItems.item')
            ->where('branch_id', $branchId)
            ->orderBy('created_at', 'desc')
            ->get();

        $transformed = $sales->flatMap(function ($sale) {
            return $sale->saleItems->map(function ($saleItem) use ($sale) {
                return [
                    'id' => $saleItem->id,
                    'item_purchased' => $saleItem->item->name ?? 'Unknown',
                    'quantity' => $saleItem->quantity,
                    'total' => $saleItem->quantity * $saleItem->price,
                    'payment_method' => $sale->payment_method,
                    'seller_name' => $sale->seller_id,
                    'timestamp' => $sale->created_at,
                ];
            });
        });

        return response()->json($transformed);
    }

    public function startMpesaPayment(Request $request)
    {
        Log::info('SaleController::startMpesaPayment', ['request' => $request->all()]);

        $data = $request->validate([
            'branch' => 'required|exists:branches,id',
            'items' => 'required|array|min:1',
            'items.*.item' => 'required|exists:inventory_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'customer_telephone_number' => 'required|string',
        ]);

        // ✅ compute total from cart
        $total = collect($data['items'])->sum(fn($i) => (float) $i['price'] * (float) $i['quantity']);

        // ✅ check stock before STK push
        foreach ($data['items'] as $item) {
            $inv = InventoryItem::find($item['item']);
            if (!$inv || $inv->stock < $item['quantity']) {
                return response()->json(['error' => "Insufficient stock for item {$item['item']}"], 400);
            }
        }

        // ✅ create unique ref
        $ref = uniqid('cart_');

        // ✅ store cart
        $cartData = [
            'branch_id' => $data['branch'],
            'items' => $data['items'],
            'amount' => $total,
            'customer_telephone' => $data['customer_telephone_number'],
            'cart_ref' => $ref,
        ];

        Cache::put($ref, $cartData, now()->addMinutes(10));

        // ✅ trigger STK push
        $mpesaService = new DynamicMpesaService($data['branch']);
        $resp = $mpesaService->stkPush(
            $data['customer_telephone_number'],
            $total,
            $ref
        );

        // ✅ map CheckoutRequestID → same cart
        if (!empty($resp['CheckoutRequestID'])) {
            Cache::put($resp['CheckoutRequestID'], $cartData, now()->addMinutes(10));
            Log::info('Mapped CheckoutRequestID to cart', [
                'cart_ref' => $ref,
                'checkout_id' => $resp['CheckoutRequestID'],
            ]);
        } else {
            Log::warning('STK push response missing CheckoutRequestID', ['response' => $resp]);
        }

        return response()->json([
            'message' => 'STK push initiated',
            'reference' => $ref,
            'mpesa_response' => $resp,
        ]);
    }


    public function mpesaStatus($reference)
    {
        Log::info('Checking M-Pesa payment status', ['reference' => $reference]);

        // if still in cache, it hasn't been paid yet
        if (Cache::has($reference)) {
            return response()->json([
                'status' => 'pending'
            ]);
        }

        // else try to find a sale that used this reference or MpesaReceiptNumber
        $sale = Sale::where('mpesa_ref', $reference)
            ->orWhere('account_reference', $reference) // if you store ref
            ->latest()
            ->first();

        if (!$sale) {
            return response()->json([
                'status' => 'not_found'
            ]);
        }

        return response()->json([
            'status' => $sale->payment_status, // Paid, Pending, etc.
            'sale' => $sale->load('saleItems.item')
        ]);
    }

    public function store(Request $request)
    {
        Log::info('SaleController::store started', ['request' => $request->all()]);

        try {
            $data = $request->validate([
                'branch' => 'required|exists:branches,id',
                'items' => 'required|array|min:1',
                'items.*.item' => 'required|exists:inventory_items,id',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.price' => 'required|numeric|min:0',
                'payment_method' => 'required|in:Cash,Credit,M-Pesa',
                'cash_tendered' => 'nullable|numeric|min:0',
                'seller_id' => 'nullable|string',
                'customer_name' => 'nullable|string',
                'customer_id_number' => 'nullable|string',
                'customer_telephone_number' => 'nullable|string',
            ]);

            // total
            $total = 0.0;
            foreach ($data['items'] as $item) {
                $total += ((float) $item['price'] * (float) $item['quantity']);
            }

            // determine payment status
            $paymentStatus = 'Paid';
            if ($data['payment_method'] === 'Credit') {
                $paymentStatus = 'Unpaid';
            }
            if ($data['payment_method'] === 'M-Pesa' && !$request->boolean('use_stkpush', true)) {
                $paymentStatus = 'Pending'; // manual till payment
            }

            // create sale
            $sale = Sale::create([
                'branch_id' => $data['branch'],
                'payment_method' => $data['payment_method'],
                'total' => $total,
                'seller_id' => $data['seller_id'] ?? null,
                'cash_tendered' => $data['cash_tendered'] ?? null,
                'change' => isset($data['cash_tendered']) ? ((float) $data['cash_tendered'] - $total) : null,
                'payment_status' => $paymentStatus,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_id_number' => $data['customer_id_number'] ?? null,
                'customer_telephone_number' => $data['customer_telephone_number'] ?? null,
            ]);

            Log::info('Sale created', ['sale_id' => $sale->id]);

            if ($sale->total > 5000) {
                $owner = User::where('role', 'Owner')->first();
                $owner->notify(new LargeTransactionNotification($sale));
            }

            // items
            $validItems = false;
            foreach ($data['items'] as $item) {
                $inventoryItem = InventoryItem::find($item['item']);
                if (!$inventoryItem) {
                    Log::warning('Inventory item not found', ['item_id' => $item['item']]);
                    continue;
                }
                if ($inventoryItem->stock < $item['quantity']) {
                    Log::error('Insufficient stock', ['item_id' => $item['item'], 'stock' => $inventoryItem->stock, 'requested' => $item['quantity']]);
                    $sale->delete();
                    return response()->json(['error' => "Insufficient stock for item ID {$item['item']}"], 400);
                }
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'item_id' => $inventoryItem->id,
                    'quantity' => (float) $item['quantity'],
                    'price' => (float) $item['price'],
                ]);
                $inventoryItem->stock -= (float) $item['quantity'];
                $inventoryItem->save();
                $validItems = true;
            }

            if (!$validItems) {
                Log::error('No valid items in sale', ['sale_id' => $sale->id]);
                $sale->delete();
                return response()->json(['error' => 'No valid items provided'], 400);
            }

            // credit
            if ($data['payment_method'] === 'Credit') {
                if (empty($data['customer_name']) || empty($data['customer_id_number']) || empty($data['customer_telephone_number'])) {
                    Log::error('Credit sale validation failed', ['request' => $data]);
                    return response()->json(['error' => 'customer_name, customer_id_number, and customer_telephone_number are required for Credit'], 400);
                }
                // Normalize phone for search
                $phone = $sale->customer_telephone_number;
                $normalizedPhone = preg_replace('/\D/', '', $phone);
                if (str_starts_with($normalizedPhone, '0')) {
                    $normalizedPhone = '254' . substr($normalizedPhone, 1);
                } elseif (!str_starts_with($normalizedPhone, '254')) {
                    $normalizedPhone = '254' . $normalizedPhone;
                }

                $credit = CreditSale::where('customer_phone', $normalizedPhone)->first();

                if ($credit) {
                    $credit->total_amount += $sale->total;
                    $credit->save();
                } else {
                    $credit = CreditSale::create([
                        'sale_id' => $sale->id,
                        'customer' => $sale->customer_name,
                        'customer_phone' => $sale->customer_telephone_number,
                        'total_amount' => $sale->total,
                        'amount_paid' => 0,
                    ]);
                }

                $sale->credit_sale_id = $credit->id;
                $sale->save();

                Log::info('Credit sale created', ['credit_sale_id' => $credit->id]);
            }

            // mpesa
            if ($data['payment_method'] === 'M-Pesa') {
                if ($request->boolean('use_stkpush', true)) {
                    $mpesaService = new DynamicMpesaService($data['branch']);
                    $response = $mpesaService->stkPush(
                        $data['customer_telephone_number'],
                        $total,
                        $sale->id
                    );
                    return response()->json([
                        'message' => 'STK push initiated',
                        'sale_id' => $sale->id,
                        'mpesa_response' => $response
                    ]);
                } else {
                    return response()->json([
                        'message' => 'Waiting for customer to pay manually to Till/Paybill',
                        'sale_id' => $sale->id,
                        'payment_status' => $sale->payment_status
                    ]);
                }
            }

            Log::info('SaleController::store completed', ['sale_id' => $sale->id]);
            return response()->json($sale->load('saleItems.item'), 201);
        } catch (\Exception $e) {
            Log::error('SaleController::store failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    public function pay(Request $request, Sale $sale)
    {
        Log::info('SaleController::pay started', ['sale_id' => $sale->id]);

        try {
            if ($sale->payment_method !== 'Credit') {
                Log::error('Non-credit sale payment attempted', ['sale_id' => $sale->id]);
                return response()->json(['error' => 'Only Credit sales can be paid later'], 400);
            }

            $data = $request->validate([
                'cash_tendered' => 'required|numeric|min:0',
            ]);

            if ($data['cash_tendered'] < $sale->total) {
                Log::error('Insufficient cash tendered', ['sale_id' => $sale->id, 'cash_tendered' => $data['cash_tendered'], 'total' => $sale->total]);
                return response()->json(['error' => 'cash_tendered must be >= total'], 400);
            }

            $sale->cash_tendered = $data['cash_tendered'];
            $sale->change = $sale->cash_tendered - $sale->total;
            $sale->payment_status = 'Paid';
            $sale->save();

            $credit = CreditSale::firstOrCreate(
                [
                    'customer' => $sale->customer_name,
                    'customer_phone' => $sale->customer_telephone_number,
                ],
                [
                    'total_amount' => $sale->total,
                    'amount_paid' => 0,
                ]
            );
            $credit->amount_paid += (float) $sale->cash_tendered;
            $credit->save();
            $sale->credit_sale_id = $credit->id;
            $sale->save();

            Log::info('SaleController::pay completed', ['sale_id' => $sale->id]);
            return response()->json($sale->load('saleItems.item'), 200);
        } catch (\Exception $e) {
            Log::error('SaleController::pay failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    public function productPerformance(Request $request, $branchId)
    {
        $period = $request->query('period', 'daily');

        $sales = Sale::with('saleItems.item')
            ->where('branch_id', $branchId)
            ->get();

        $grouped = [];

        foreach ($sales as $sale) {
            foreach ($sale->saleItems as $item) {
                $productName = $item->item->name ?? 'Unknown';
                $date = \Carbon\Carbon::parse($sale->created_at);
                switch ($period) {
                    case 'weekly':
                        $label = $date->format('o-\WW');
                        break;
                    case 'monthly':
                        $label = $date->format('Y-m');
                        break;
                    case 'yearly':
                        $label = $date->format('Y');
                        break;
                    default:
                        $label = $date->format('Y-m-d');
                }
                $grouped[$productName][$label] = ($grouped[$productName][$label] ?? 0) + ($item->quantity * $item->price);
            }
        }

        $response = [];
        foreach ($grouped as $productName => $dates) {
            foreach ($dates as $label => $total) {
                $response[] = [
                    'product' => $productName,
                    'period' => $label,
                    'total' => $total,
                ];
            }
        }

        return response()->json($response);
    }

    public function branchStatistics(Request $request, $branchId)
    {
        $branch = \App\Models\Branch::findOrFail($branchId);
        $period = $request->query('period', 'daily');

        // 🔍 Find most recent sale for this branch
        $latestSale = \App\Models\Sale::where('branch_id', $branchId)
            ->latest('created_at')
            ->first();

        // If no sales, use today so it still returns structure
        $now = $latestSale ? \Carbon\Carbon::parse($latestSale->created_at) : now();

        // 🗓️ Define date ranges and SQL format
        switch ($period) {
            case 'daily':
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                $groupFormatSqlite = '%H';
                $groupFormatMysql = '%H';
                $labelFormat = 'H:i';
                $rangeLabel = $start->toDateString();
                break;

            case 'weekly':
                $start = $now->copy()->startOfWeek();
                $end = $now->copy()->endOfWeek();
                $groupFormatSqlite = '%Y-%m-%d';
                $groupFormatMysql = '%Y-%m-%d';
                $labelFormat = 'Y-m-d';
                $rangeLabel = $start->toDateString() . ' → ' . $end->toDateString();
                break;

            case 'yearly':
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfYear();
                $groupFormatSqlite = '%Y-%m';
                $groupFormatMysql = '%Y-%m';
                $labelFormat = 'Y-m';
                $rangeLabel = $start->year;
                break;

            default: // monthly
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                $groupFormatSqlite = '%Y-%m-%d';
                $groupFormatMysql = '%Y-%m-%d';
                $labelFormat = 'Y-m-d';
                $rangeLabel = $start->format('F Y');
                break;
        }

        // 🧠 Pick SQL function based on DB driver
        $connection = \DB::connection()->getDriverName();
        $dateExpr = $connection === 'sqlite'
            ? "strftime('{$groupFormatSqlite}', created_at)"
            : "DATE_FORMAT(created_at, '{$groupFormatMysql}')";

        // 📊 Grouped Sales Query
        $sales = \App\Models\Sale::where('branch_id', $branchId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("$dateExpr as label, SUM(total) as total")
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        // 🪄 Fill in missing labels for completeness
        $filled = collect();

        if ($period === 'daily') {
            for ($h = 0; $h < 24; $h++) {
                $hour = str_pad($h, 2, '0', STR_PAD_LEFT);
                $filled->push([
                    'label' => "{$hour}:00",
                    'sales' => (float) ($sales->firstWhere('label', $hour)->total ?? 0),
                ]);
            }
        } elseif ($period === 'weekly' || $period === 'monthly') {
            $range = \Carbon\CarbonPeriod::create($start, $end);
            foreach ($range as $date) {
                $label = $date->format('Y-m-d');
                $filled->push([
                    'label' => $label,
                    'sales' => (float) ($sales->firstWhere('label', $label)->total ?? 0),
                ]);
            }
        } else {
            for ($m = 1; $m <= 12; $m++) {
                $label = $now->year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
                $filled->push([
                    'label' => $label,
                    'sales' => (float) ($sales->firstWhere('label', $label)->total ?? 0),
                ]);
            }
        }

        // 💰 Totals (always computed from actual dates)
        $totals = [
            'daily_sales' => round($branch->sales()->whereDate('created_at', $now)->sum('total'), 2),
            'weekly_sales' => round($branch->sales()->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])->sum('total'), 2),
            'monthly_sales' => round($branch->sales()->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->sum('total'), 2),
            'yearly_sales' => round($branch->sales()->whereYear('created_at', $now->year)->sum('total'), 2),
        ];

        // 🎯 Return full payload
        return response()->json([
            'branch' => $branch->name,
            'period' => $period,
            'range' => $rangeLabel, // 👈 new field (human-readable)
            'data' => $filled->values(),
            'totals' => $totals,
            'targets' => [
                'daily_target' => (float) $branch->daily_target,
                'weekly_target' => (float) $branch->weekly_target,
                'monthly_target' => (float) $branch->monthly_target,
                'yearly_target' => (float) $branch->yearly_target,
            ],
        ], 200);
    }



}























